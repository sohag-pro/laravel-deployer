<p align="center">
  <a href="https://laravel.com" target="_blank">
    <img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="320" alt="Laravel Logo">
  </a>
</p>

<h1 align="center">Laravel Deployer</h1>

<p align="center">
  A tiny, self-hosted, zero-downtime deployer for Laravel apps — with a web UI for deploys, instant rollbacks, and database backups.
</p>

<p align="center">
  <a href="https://opensource.org/licenses/MIT"><img src="https://img.shields.io/badge/license-MIT-blue.svg" alt="License: MIT"></a>
  <img src="https://img.shields.io/badge/PHP-%5E8.3-777bb4.svg" alt="PHP ^8.3">
  <img src="https://img.shields.io/badge/Laravel-%5E13-ff2d20.svg" alt="Laravel ^13">
</p>

---

## What it is

Laravel Deployer is a small Laravel application you run **on your server**. It gives you a password-protected dashboard to:

- **Deploy** the latest commit of your app with **zero downtime** (atomic symlink switch).
- **Roll back** to any previous release with one click.
- **Back up** your app's database automatically before every deploy, and **restore** or **download** any dump from the UI.
- Keep **shared** `storage/` and `.env` across releases, the way Laravel expects.

It is intentionally minimal — no agents, no queues, no external services. Just Git, PHP, and symlinks.

> 📖 Original write-up: [Laravel Deployer — The Ultimate Deployment Tool](https://notes.sohag.pro/laravel-deployer-the-ultimate-deployment-tool-for-your-laravel-application)

---

## How it works

Each deploy clones a **fresh, timestamped release** and then atomically re-points your web root at it. Nothing is built in place, so a half-finished deploy never serves traffic.

```
BASE_DIR/                         # working area (NOT web-served)
├── backups/                      # VERSION_DIR — one folder per release
│   ├── 2024-05-01__10_00_00/     # a release (git clone + vendor + caches)
│   └── 2024-05-02__09_30_00/     # the newest release
├── storage/                      # STORAGE_DIR — shared across all releases
├── database/                     # DB_DIR — timestamped .sql dumps
└── .env                          # shared .env, symlinked into each release

SERVE_DIR/  ──(symlinks)──▶  BASE_DIR/backups/<newest-release>/*
   ▲
   └── your web server's document root (e.g. nginx/apache points here)
```

A single deploy run (`php artisan deploy`):

1. Clones `GIT_REMOTE_URL` into a new `VERSION_DIR/<timestamp>` release.
2. On the first deploy, seeds shared `storage/` and creates the shared `.env` from `.env.example`.
3. Replaces the release's `storage/` with a symlink to the shared one, and links the shared `.env` in.
4. Dumps the configured database to `DB_DIR/<db>-<timestamp>.sql` (if DB creds are set).
5. Runs the build (`composer install && php artisan optimize:clear`) and your `AFTER_DEPLOY_COMMANDS`, plus an optional project `afterDeploy.sh`.
6. Atomically switches `SERVE_DIR` to symlink the new release — this is the moment the new version goes live.

**Rollback** just re-points `SERVE_DIR` at an older release directory. **Restore DB** pipes a chosen dump back into the database.

---

## Requirements

- PHP **8.3+** with the `zip` extension
- Composer
- Git (available on `PATH`)
- A database for the deployer itself (SQLite is fine; MySQL/MariaDB also supported)
- `mysqldump` / `mysql` clients **on the server** if you want automatic DB backup/restore of the deployed app
- A web server (nginx/Apache) able to follow symlinks, or `php artisan serve` for local trials

---

## Installation

### 1. Get the code

```bash
git clone https://github.com/sohag-pro/laravel-deployer.git
cd laravel-deployer
composer install
cp .env.example .env
php artisan key:generate
```

### 2. Configure `.env`

Set the deployment target and (optionally) the database to back up. See the [Configuration](#configuration) table below. At minimum:

```dotenv
GIT_REMOTE_URL=git@github.com:you/your-app.git
SERVE_DIR=/var/www/your-app/public_html/    # your web root, trailing slash
BASE_DIR=/var/www/your-app/                  # working dir, different from SERVE_DIR
```

### 3. Create the admin login

Migrate the deployer's own database, then create the admin account. Set `ADMIN_EMAIL` / `ADMIN_PASSWORD` in `.env` first, or leave `ADMIN_PASSWORD` blank to have a strong password generated and printed once.

```bash
php artisan migrate
php artisan db:seed --class=AdminSeeder
```

### 4. Run it

For a quick local try:

```bash
php artisan serve
# visit http://localhost:8000 and sign in
```

For production, point your web server at the deployer's own `public/` directory and serve it over **HTTPS** (see [Security](#security)).

---

## Usage

1. Sign in at `/` with the admin account.
2. **Deploy** — click *Deploy*. A new release is cloned, built and switched in. The currently-live release is highlighted green.
3. **Roll back** — in *Files*, click *Restore* on any previous release to make it live again.
4. **Database** — every deploy leaves a dump in the list. *Download* it, or *Restore* to pipe it back into the database.

All actions require confirmation and are protected by authentication and CSRF.

---

## Configuration

All deployment settings live in `config/deployer.php` and are driven by `.env`.

| Variable | Purpose | Example |
| --- | --- | --- |
| `GIT_REMOTE_URL` | Repository to deploy | `git@github.com:you/app.git` |
| `SERVE_DIR` | Web root; live release is symlinked here (trailing slash) | `/var/www/app/public_html/` |
| `BASE_DIR` | Working dir for releases/storage/db/.env (trailing slash, **≠ `SERVE_DIR`**) | `/var/www/app/` |
| `VERSION_DIR` | Releases subfolder under `BASE_DIR` | `backups` |
| `STORAGE_DIR` | Shared storage subfolder under `BASE_DIR` | `storage` |
| `DB_DIR` | DB dumps subfolder under `BASE_DIR` | `database` |
| `AFTER_DEPLOY_COMMANDS` | Comma-separated commands run in each release | `php artisan config:cache,php artisan route:cache` |
| `DEPLOYER_DB_NAME` | Database of the **deployed app** to dump/restore | `myapp` |
| `DEPLOYER_DB_USER` / `DEPLOYER_DB_PASSWORD` | Credentials for that database | |
| `DEPLOYER_DB_HOST` / `DEPLOYER_DB_PORT` | Host/port for that database | `127.0.0.1` / `3306` |
| `ADMIN_EMAIL` / `ADMIN_PASSWORD` | Dashboard login (seeded once) | |

**Per-project build hook:** if a deployed repo contains an `afterDeploy.sh` at its root, it runs inside the release after the standard build step — use it for `npm ci && npm run build`, migrations, etc.

---

## Security

This tool can run arbitrary deploy commands, overwrite your web root, and restore databases. Treat the deployer instance as **privileged infrastructure**.

- **Authentication is mandatory.** Every dashboard and action route is behind the `auth` middleware; there are no default credentials.
- **Login is rate-limited.** After 5 failed attempts per email + IP, further attempts are locked out for 60 seconds (a `Lockout` event is dispatched).
- **Destructive actions are POST + CSRF only** (deploy, restore, DB restore). Read-only downloads are GET.
- **No shell injection.** All user-supplied names are validated against `^[A-Za-z0-9._-]+$` (no traversal, no metacharacters) and every shell argument is escaped via `escapeshellarg`.
- **DB credentials never hit the process list** — `mysqldump`/`mysql` receive them through a temporary `0600` option file, not `-p<password>`.
- **Run it behind HTTPS** and, ideally, restrict access by IP / VPN / basic-auth at the web-server layer.
- Set `APP_DEBUG=false` and `APP_ENV=production` in production.
- The OS user running the deployer needs write access to `BASE_DIR` and `SERVE_DIR`; scope it tightly.

Found a vulnerability? Please open a private report rather than a public issue.

---

## Testing

```bash
composer install
vendor/bin/phpunit       # run the suite
vendor/bin/pint          # format / lint
```

The suite covers authentication gating and rejection of malicious filenames.

---

## Contributing

Contributions are welcome — see [CONTRIBUTING.md](CONTRIBUTING.md). Bug reports, feature ideas, docs and code are all appreciated. Please run `vendor/bin/pint` and `vendor/bin/phpunit` before opening a PR.

---

## License

Laravel Deployer is open-source software licensed under the [MIT license](LICENSE).
