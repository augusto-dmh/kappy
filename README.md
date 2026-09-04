# Kappy

Kappy is an AI GitHub PR reviewer. A GitHub App watches opted-in repositories, generates structured findings, posts a summary and inline comments on the pull request, and keeps a tenanted review inbox in the app.

## Stack

Laravel 13 modular monolith (PHP 8.4), Inertia.js v3 with React 19, Tailwind CSS v4, Fortify, Wayfinder. Domain modules live in `app-modules/`.

## Modules

| Module | What it owns |
| --- | --- |
| `identity` | Accounts, memberships, GitHub OAuth |
| `github-app` | App install, webhooks, repositories, SCM driver |
| `review` | Review pipeline, findings, inbox pages, posting |

## Setup

Requires PHP 8.4+ and Node.js.

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run dev
```

`composer run dev` starts the HTTP server, queue worker, logs, and Vite together.

Copy GitHub OAuth and GitHub App values from `.env.example` (`GITHUB_*`, `GITHUB_APP_*`). Sign in with GitHub, install the App, then enable reviews per repository at `/repositories`. Open `/reviews` for the in-app inbox. `migrate --seed` walks that inbox without a live GitHub App.

## License

MIT
