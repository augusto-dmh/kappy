#!/usr/bin/env bash
#
# tunnel.sh — expose a local port to the public internet via a tunnel.
#
# A generic, reusable dev helper for ANY test that needs an externally
# reachable URL: GitHub / Stripe / Twilio webhooks, OAuth callbacks,
# sharing a local preview, etc. It auto-detects a tunnel provider
# (cloudflared, ngrok, or expose), prints the public URL (optionally with
# a path appended), copies it to the clipboard, and can write it to a file
# so other scripts can pick it up.
#
# Usage:
#   scripts/tunnel.sh [PORT] [options]
#
# Options:
#   -p, --path PATH      Append PATH to the printed URL (e.g. /webhooks/github)
#       --provider NAME  Force a provider: cloudflared | ngrok | expose
#   -o, --out FILE       Write the public base URL to FILE (for other scripts)
#   -h, --help           Show this help and exit
#
# Examples:
#   scripts/tunnel.sh                              # expose http://localhost:8000
#   scripts/tunnel.sh 8000 -p /webhooks/github     # print <url>/webhooks/github
#   scripts/tunnel.sh 5173 --provider ngrok
#   scripts/tunnel.sh 8000 -o storage/app/tunnel-url.txt
#
# Leave it running; Ctrl-C stops the tunnel.

set -euo pipefail

PORT=8000
PATH_SUFFIX=""
PROVIDER=""
OUT_FILE=""

c_cyan='\033[36m'; c_yellow='\033[33m'; c_red='\033[31m'; c_green='\033[32m'; c_bold='\033[1m'; c_off='\033[0m'
log()  { printf "${c_cyan}[tunnel]${c_off} %s\n" "$*"; }
warn() { printf "${c_yellow}[tunnel] %s${c_off}\n" "$*" >&2; }
die()  { printf "${c_red}[tunnel] %s${c_off}\n" "$*" >&2; exit 1; }

usage() { awk 'NR==1 && /^#!/ {next} /^#/ {sub(/^# ?/,""); print; next} {exit}' "$0"; }

# --- parse args ----------------------------------------------------------
while [ $# -gt 0 ]; do
  case "$1" in
    -p|--path)     PATH_SUFFIX="${2:-}"; shift 2 ;;
    --provider)    PROVIDER="${2:-}"; shift 2 ;;
    -o|--out)      OUT_FILE="${2:-}"; shift 2 ;;
    -h|--help)     usage; exit 0 ;;
    ''|*[!0-9]*)   die "Unknown argument: $1 (see --help)" ;;
    *)             PORT="$1"; shift ;;
  esac
done

# normalize a leading slash on the path
if [ -n "$PATH_SUFFIX" ] && [ "${PATH_SUFFIX#/}" = "$PATH_SUFFIX" ]; then
  PATH_SUFFIX="/$PATH_SUFFIX"
fi

LOGFILE="${TMPDIR:-/tmp}/kappy-tunnel-${PORT}.log"
ANNOUNCED=0

# --- helpers -------------------------------------------------------------
copy_clip() {
  if command -v wl-copy >/dev/null 2>&1; then
    printf '%s' "$1" | wl-copy 2>/dev/null && log "copied to clipboard (wl-copy)" || true
  elif command -v xclip >/dev/null 2>&1; then
    printf '%s' "$1" | xclip -selection clipboard 2>/dev/null && log "copied to clipboard (xclip)" || true
  fi
}

announce() {
  [ "$ANNOUNCED" -eq 1 ] && return 0
  ANNOUNCED=1
  local base="$1" full="${1}${PATH_SUFFIX}" bar
  bar="$(printf '=%.0s' $(seq 1 60))"
  printf '\n%b%s%b\n' "$c_green" "$bar" "$c_off"
  printf "  Public URL:  ${c_bold}%s${c_off}\n" "$full"
  printf '%b%s%b\n\n' "$c_green" "$bar" "$c_off"
  if [ -n "$OUT_FILE" ]; then
    mkdir -p "$(dirname "$OUT_FILE")"
    printf '%s\n' "$base" > "$OUT_FILE"
    log "wrote base URL to $OUT_FILE"
  fi
  copy_clip "$full"
}

pick_provider() {
  if [ -n "$PROVIDER" ]; then
    command -v "$PROVIDER" >/dev/null 2>&1 || die "Requested provider '$PROVIDER' is not installed."
    printf '%s' "$PROVIDER"; return
  fi
  local p
  for p in cloudflared ngrok expose; do
    command -v "$p" >/dev/null 2>&1 && { printf '%s' "$p"; return; }
  done
  die "No tunnel provider found. Install one:
    cloudflared (no signup, recommended):
      curl -L -o cloudflared.deb https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-amd64.deb && sudo dpkg -i cloudflared.deb
    ngrok (free account for the authtoken):
      sudo snap install ngrok && ngrok config add-authtoken <token-from-dashboard.ngrok.com>
    expose (Laravel-native):
      composer global require beyondcode/expose"
}

run_cloudflared() {
  log "Cloudflare quick tunnel -> http://localhost:${PORT}"
  cloudflared tunnel --url "http://localhost:${PORT}" 2>&1 | while IFS= read -r line; do
    printf '%s\n' "$line"
    if [[ "$line" =~ https://[A-Za-z0-9.-]+\.trycloudflare\.com ]]; then
      announce "${BASH_REMATCH[0]}"
    fi
  done
}

run_ngrok() {
  log "ngrok -> http://localhost:${PORT}"
  ngrok http "${PORT}" --log=stdout >"$LOGFILE" 2>&1 &
  local pid=$! url=""
  trap 'kill "$pid" 2>/dev/null || true' EXIT INT TERM
  local i
  for i in $(seq 1 30); do
    if ! kill -0 "$pid" 2>/dev/null; then
      warn "ngrok exited early — see $LOGFILE"
      warn "If it's an auth error: ngrok config add-authtoken <token-from-dashboard.ngrok.com>"
      exit 1
    fi
    url="$(curl -fsS http://127.0.0.1:4040/api/tunnels 2>/dev/null \
      | grep -oE 'https://[A-Za-z0-9.-]+\.ngrok[A-Za-z0-9.-]*' | head -1 || true)"
    [ -n "$url" ] && break
    sleep 1
  done
  [ -n "$url" ] || die "No ngrok public URL after 30s — see $LOGFILE"
  announce "$url"
  log "ngrok inspector: http://127.0.0.1:4040  (inspect/replay requests)"
  wait "$pid"
}

run_expose() {
  log "expose -> http://localhost:${PORT}"
  expose share "http://localhost:${PORT}" 2>&1 | while IFS= read -r line; do
    printf '%s\n' "$line"
    if [[ "$line" =~ https://[A-Za-z0-9.-]+\.(sharedwithexpose\.com|expose\.dev|[A-Za-z0-9.-]+) ]] && [[ "$line" == *https* ]]; then
      announce "$(printf '%s' "$line" | grep -oE 'https://[A-Za-z0-9./-]+' | head -1)"
    fi
  done
}

# --- run -----------------------------------------------------------------
[ "$PORT" -gt 0 ] 2>/dev/null || die "Invalid port: $PORT"

if ! curl -fsS -o /dev/null --max-time 2 "http://localhost:${PORT}" 2>/dev/null; then
  warn "Nothing answered on http://localhost:${PORT} yet."
  warn "Start your app first (e.g. 'composer run dev'); the tunnel still connects once it's up."
fi

provider="$(pick_provider)"
case "$provider" in
  cloudflared) run_cloudflared ;;
  ngrok)       run_ngrok ;;
  expose)      run_expose ;;
  *)           die "Unsupported provider: $provider" ;;
esac
