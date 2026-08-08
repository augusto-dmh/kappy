#!/usr/bin/env python3
"""Post kappy-review-triage dispositions onto a PR's review threads.

Usage:
  post_dispositions.py <pr_number> <plan.json> [--dry-run]

plan.json:
  { "repo": "owner/name",
    "items": [
      { "comment_id": 123, "tag": "RESOLVED", "body": "[RESOLVED] Resolvido em e38ab3a — …", "resolve": true },
      { "comment_id": 124, "tag": "ADIADO",   "body": "[ADIADO] Mantido aberto de propósito. …", "resolve": false },
      { "comment_id": 125, "tag": "INVALIDO", "body": "[INVÁLIDO] Não procede — …", "resolve": true },
      { "comment_id": 126, "tag": "FLAG" }
    ] }

- `comment_id` is the root review comment's databaseId (the thread to reply under).
- FLAG items (or any item without a body) are skipped — they get no comment.
- RESOLVED / INVALIDO items resolve their thread after the reply is posted; ADIADO stays open.
- Always run with --dry-run first to preview exactly what will be posted.
"""
import argparse, json, subprocess, sys


def gh(args, inp=None):
    return subprocess.run(["gh"] + args, input=inp, capture_output=True, text=True)


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("pr")
    ap.add_argument("plan")
    ap.add_argument("--dry-run", action="store_true")
    a = ap.parse_args()

    with open(a.plan, encoding="utf-8") as fh:
        plan = json.load(fh)
    repo = plan["repo"]
    owner, name = repo.split("/", 1)
    items = [it for it in plan["items"] if it.get("body") and it.get("tag") != "FLAG"]
    flags = [it for it in plan["items"] if it.get("tag") == "FLAG" or not it.get("body")]

    # map root comment databaseId -> thread node id (needed to resolve)
    q = ('{ repository(owner:"%s", name:"%s"){ pullRequest(number:%s){ reviewThreads(first:100){ nodes{ '
         'id comments(first:1){ nodes{ databaseId } } } } } } }') % (owner, name, a.pr)
    r = gh(["api", "graphql", "-f", "query=" + q])
    if r.returncode != 0:
        print("FAIL fetching threads:", r.stderr.strip()[:300])
        sys.exit(1)
    nodes = json.loads(r.stdout)["data"]["repository"]["pullRequest"]["reviewThreads"]["nodes"]
    root_to_thread = {t["comments"]["nodes"][0]["databaseId"]: t["id"]
                      for t in nodes if t["comments"]["nodes"]}

    results, failed = [], False
    for it in items:
        cid, body, tag = it["comment_id"], it["body"], it["tag"]
        resolve = bool(it.get("resolve", False))
        if a.dry_run:
            results.append(f"DRY  [{tag}] reply to {cid}{' + resolve' if resolve else ' (keep open)'}: {body[:110]}")
            continue
        rr = gh(["api", f"repos/{repo}/pulls/{a.pr}/comments/{cid}/replies", "--method", "POST", "--input", "-"],
                inp=json.dumps({"body": body}))
        if rr.returncode != 0:
            results.append(f"FAIL reply [{tag}] {cid}: {rr.stderr.strip()[:160]}")
            failed = True
            continue
        url = json.loads(rr.stdout).get("html_url")
        if not resolve:
            results.append(f"OK   [{tag}] {cid} replied (open): {url}")
            continue
        tid = root_to_thread.get(cid)
        if not tid:
            results.append(f"WARN [{tag}] {cid} replied ({url}) but no thread id to resolve")
            failed = True
            continue
        mut = "mutation($id:ID!){ resolveReviewThread(input:{threadId:$id}){ thread{ isResolved } } }"
        mr = gh(["api", "graphql", "-f", "query=" + mut, "-f", "id=" + tid])
        if mr.returncode == 0 and '"isResolved":true' in mr.stdout.replace(" ", ""):
            results.append(f"OK   [{tag}] {cid} replied + resolved: {url}")
        else:
            results.append(f"WARN [{tag}] {cid} reply ok, resolve FAILED: {(mr.stderr or mr.stdout).strip()[:140]}")
            failed = True

    for it in flags:
        results.append(f"SKIP [FLAG] {it.get('comment_id')} — no comment; route to pr-execute")

    print("\n".join(results))
    sys.exit(1 if failed else 0)


if __name__ == "__main__":
    main()
