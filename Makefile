# ── Local development ──────────────────────────────────────────────
# `make dev`: a Cloudflare tunnel (its URL written into .env as
# PAYZONE_CALLBACK_BASE_URL) plus `composer dev`. Ctrl+C stops both.
# `make tunnel`: the tunnel alone, when `composer dev` is already running.
# See scripts/dev-tunnel.ps1. Windows / PowerShell only.
dev:
	powershell -NoProfile -ExecutionPolicy Bypass -File scripts/dev-tunnel.ps1

tunnel:
	powershell -NoProfile -ExecutionPolicy Bypass -File scripts/dev-tunnel.ps1 -TunnelOnly

reset:
	php artisan migrate:fresh
	php artisan db:seed
	php artisan db:seed --class=BuyerCartSeeder
	php artisan db:seed --class=SuperAdminSeeder

# ── VPS deployment ──────────────────────────────────────────────────────
# Three compose stacks share one Debian VPS:
#
#   proxy    docker-compose.proxy.yml    owns :80/:443, TLS, certbot, routing
#   prod     docker-compose.prod.yml     hirfaty.com        (main branch)
#   staging  docker-compose.staging.yml  init.hirfaty.com   (staging branch)
#
# The app stacks bind no host ports; the proxy reaches them over the external
# `hirfaty-edge` network via the aliases app-prod / app-staging.
#
# Expected layout — two checkouts of this repo, one per branch:
#   /opt/hirfaty          (main)    → runs the proxy + prod targets
#   /opt/hirfaty-staging  (staging) → runs the stage targets
#
# One-time host setup:
#   sudo cp docker/systemd/hirfaty.service /etc/systemd/system/hirfaty.service
#   sudo systemctl daemon-reload && sudo systemctl enable hirfaty

# Both app stacks keep their real secrets in a file named plainly `.env` in
# their own checkout (prod's ~/prod-hirfaty/.env, staging's
# ~/staging-hirfaty/.env — two different files that just share a filename).
#
# --env-file is explicit here on purpose, not decorative. `${VAR:-default}`
# interpolation inside a compose YAML (a separate mechanism from `env_file:`
# on a service) is resolved by Compose itself at parse time from EITHER the
# shell's exported vars OR a file literally named `.env` in the working
# directory. Naming staging's file anything else (it used to be
# `.env.staging`) makes every ${DB_USERNAME:-hirfaty}-style default silently
# win regardless of what the file says — that's exactly what caused the
# staging db to init with the wrong credentials once already. Passing
# --env-file explicitly is defense against a repeat if this ever runs from a
# different cwd or the filename convention drifts again.
COMPOSE = docker compose -f docker-compose.prod.yml --env-file .env
COMPOSE_STAGE = docker compose -f docker-compose.staging.yml --env-file .env
COMPOSE_PROXY = docker compose -f docker-compose.proxy.yml

PROD_DOMAIN ?= hirfaty.com
STAGING_DOMAIN ?= init.hirfaty.com
CERT_EMAIL ?= creati.maroc@gmail.com
EDGE_NETWORK = hirfaty-edge

.PHONY: dev tunnel reset edge-network \
	prod-build prod-up prod-down prod-restart prod-logs prod-ps \
	prod-migrate prod-db-reset prod-seed prod-artisan prod-key-generate \
	prod-pull prod-release prod-deploy prod-backup prod-backup-list prod-backup-check \
	media-browser media-browser-stop \
	stage-build stage-key-generate stage-up stage-down stage-restart stage-logs stage-ps \
	stage-migrate stage-db-reset stage-seed stage-artisan \
	stage-backup stage-backup-list stage-backup-check \
	stage-pull stage-release stage-deploy \
	proxy-build proxy-up proxy-down proxy-restart proxy-logs proxy-reload \
	proxy-htpasswd proxy-cert-prod proxy-cert-staging proxy-cert-renew

# The shared network both app stacks and the proxy attach to. Safe to re-run.
edge-network:
	@docker network inspect $(EDGE_NETWORK) >/dev/null 2>&1 \
		|| docker network create $(EDGE_NETWORK)

# ── Proxy (run from the production checkout) ────────────────────────────

proxy-build:
	$(COMPOSE_PROXY) build

# Fails fast if docker/proxy/htpasswd is missing — see proxy-htpasswd.
proxy-up: edge-network
	$(COMPOSE_PROXY) up -d proxy

proxy-down:
	$(COMPOSE_PROXY) down

proxy-restart:
	$(COMPOSE_PROXY) restart proxy

proxy-logs:
	$(COMPOSE_PROXY) logs -f --tail=200 proxy

# Reloads nginx without dropping connections. Use after a cert renewal;
# a vhost *fragment* change needs proxy-build + proxy-up instead, since the
# fragments are baked into the image.
proxy-reload:
	$(COMPOSE_PROXY) exec proxy nginx -s reload

# Creates/overwrites the basic-auth file guarding staging.
# Usage: make proxy-htpasswd USER=hirfaty PASS='some-strong-password'
proxy-htpasswd:
	@if [ -z "$(USER)" ] || [ -z "$(PASS)" ]; then \
		echo "Usage: make proxy-htpasswd USER=<user> PASS=<password>"; \
		exit 1; \
	fi
	@docker run --rm httpd:2.4-alpine htpasswd -nbB "$(USER)" "$(PASS)" \
		> docker/proxy/htpasswd
	@chmod 644 docker/proxy/htpasswd
	@echo "Wrote docker/proxy/htpasswd for user '$(USER)'."

# TLS bootstrap. The proxy must already be serving HTTP (`make proxy-up`) so
# certbot can complete the HTTP-01 challenge through the shared webroot.
# After issuing, proxy-up re-runs the entrypoint so it switches that domain
# from its -http fragment to the -ssl one.
proxy-cert-prod:
	$(COMPOSE_PROXY) run --rm certbot certonly --webroot -w /var/www/certbot \
		-d $(PROD_DOMAIN) -d www.$(PROD_DOMAIN) \
		--email $(CERT_EMAIL) --agree-tos --no-eff-email
	$(COMPOSE_PROXY) up -d --force-recreate proxy

proxy-cert-staging:
	$(COMPOSE_PROXY) run --rm certbot certonly --webroot -w /var/www/certbot \
		-d $(STAGING_DOMAIN) \
		--email $(CERT_EMAIL) --agree-tos --no-eff-email
	$(COMPOSE_PROXY) up -d --force-recreate proxy

proxy-cert-renew:
	$(COMPOSE_PROXY) run --rm certbot renew
	$(COMPOSE_PROXY) exec proxy nginx -s reload

# ── Production (hirfaty.com) ────────────────────────────────────────────

prod-build:
	$(COMPOSE) build

prod-up: edge-network
	$(COMPOSE) up -d db seaweedfs
	$(COMPOSE) up -d app queue scheduler

prod-down:
	$(COMPOSE) down

prod-restart:
	$(COMPOSE) restart app queue scheduler

prod-logs:
	$(COMPOSE) logs -f --tail=200

prod-ps:
	$(COMPOSE) ps

prod-migrate:
	$(COMPOSE) exec app php artisan migrate --force

# Drops every table, re-migrates from scratch, and reseeds only the super
# admin account — irreversible, all data is lost. Requires typing "reset" at
# the prompt; anything else aborts. Take a `make prod-backup` first.
#
# `migrate:fresh` is normally refused in production (see
# DB::prohibitDestructiveCommands in AppServiceProvider) even with --force,
# which is a *different* guard than --force bypasses — so this one-off `run`
# gets APP_ENV=local injected to disable it, without touching the real
# APP_ENV=production the long-running app/queue/scheduler containers use.
# db:seed isn't covered by that guard, so it needs no override.
prod-db-reset:
	@echo "This will DROP ALL TABLES in the production database. All data will be lost."
	@read -p "Type 'reset' to confirm: " confirm; \
	if [ "$$confirm" != "reset" ]; then \
		echo "Aborted."; \
		exit 1; \
	fi
	$(COMPOSE) run --rm -e APP_ENV=local app php artisan migrate:fresh --force
	$(COMPOSE) run --rm app php artisan db:seed --class=SuperAdminSeeder --force

prod-seed:
	$(COMPOSE) run --rm app php artisan db:seed --force

# Usage: make prod-artisan CMD="queue:restart"
prod-artisan:
	$(COMPOSE) exec app php artisan $(CMD)

# There's no .env file inside the container (env_file only injects process
# env vars), so `artisan key:generate` has nothing to write to. This prints a
# key instead — paste it into APP_KEY= in your VPS .env, then `make prod-restart`.
prod-key-generate:
	docker run --rm --entrypoint php hirfaty-app:latest artisan key:generate --show

# Rebuild, migrate and restart from whatever is already checked out: rebuild
# the image, run migrations, then recreate app/queue/scheduler with the new
# image (db/seaweedfs untouched). No git step, for the same reason as
# stage-release — CI hard-resets to origin/main itself, then calls this.
prod-release: edge-network
	$(COMPOSE) build app
	$(COMPOSE) up -d db seaweedfs
	$(COMPOSE) run --rm app php artisan migrate --force
	$(COMPOSE) up -d app queue scheduler

prod-pull:
	git pull

# What you run by hand on the box: pull, then release.
prod-deploy: prod-pull prod-release

# Database, every photo and this checkout's untracked config, encrypted here
# and streamed to one dated folder in Cloudflare R2 (prod/ in the bucket) — see
# docker/scripts/backup.sh. Scheduled nightly by hirfaty-backup.timer; run by
# hand before anything risky (a migration, a SeaweedFS change). All the backup
# targets need sudo: the settings in /etc/hirfaty/backup.env are root-only.
prod-backup:
	BACKUP_ENV=prod ./docker/scripts/backup.sh

# Every backup in R2, newest first. A folder without _complete shows as FAILED.
prod-backup-list:
	./docker/scripts/restore.sh list prod

# Proves a backup restores, touching nothing live: asks for the private key,
# loads the dump into a throwaway Postgres, prints row counts, lists the media
# archive, removes everything. Newest complete backup, or BACKUP=<name>. A real
# restore is described at the top of docker/scripts/restore.sh.
prod-backup-check:
	./docker/scripts/restore.sh check prod $(BACKUP)

# The read-only photo browser (docker/media-browser): live photos plus any
# photo archive uploaded through its page. Off unless started; see
# docker-compose.prod.yml.
media-browser:
	$(COMPOSE) --profile tools up -d --build media-browser
	@echo "Running. From your computer: ssh -L 8090:127.0.0.1:8090 <user>@<server>, then open http://localhost:8090"

# Stops it and throws away any archive copies it had opened. Archives uploaded
# through its page stay until deleted there.
media-browser-stop:
	$(COMPOSE) --profile tools rm -sf media-browser

# ── Staging (init.hirfaty.com) ──────────────────────────────────────────
# Same targets, against the staging stack. APP_ENV=staging in this checkout's
# .env means Laravel's destructive-command guard is off, so stage-db-reset
# needs no APP_ENV override and prompts for nothing — staging is disposable.

stage-build:
	$(COMPOSE_STAGE) build

# Same reasoning as prod-key-generate: no .env file lives inside the
# container, so this just prints a key to paste into this checkout's .env by hand.
# Needs an image to run against — `make stage-build` first if this is a
# fresh checkout.
stage-key-generate:
	docker run --rm --entrypoint php hirfaty-staging-app:latest artisan key:generate --show

stage-up: edge-network
	$(COMPOSE_STAGE) up -d db seaweedfs
	$(COMPOSE_STAGE) up -d app queue scheduler

stage-down:
	$(COMPOSE_STAGE) down

stage-restart:
	$(COMPOSE_STAGE) restart app queue scheduler

stage-logs:
	$(COMPOSE_STAGE) logs -f --tail=200

stage-ps:
	$(COMPOSE_STAGE) ps

stage-migrate:
	$(COMPOSE_STAGE) exec app php artisan migrate --force

# stage-db-reset:
# 	$(COMPOSE_STAGE) run --rm app php artisan migrate:fresh --force
# 	$(COMPOSE_STAGE) run --rm app php artisan db:seed --class=SuperAdminSeeder --force

stage-seed:
	$(COMPOSE_STAGE) run --rm app php artisan db:seed --force

# Usage: make stage-artisan CMD="queue:restart"
stage-artisan:
	$(COMPOSE_STAGE) exec app php artisan $(CMD)

# Same script as prod-backup, pointed at the staging containers and a
# separate prefix (staging/ in the same bucket) — staging is still disposable
# (stage-db-reset wipes it without ceremony) but that's not the same as "not
# worth a backup you can grab before trying something risky against
# init.hirfaty.com". Not on a timer by default the way prod is; run by hand, or
# install hirfaty-staging-backup.timer if it needs to become routine.
stage-backup:
	BACKUP_ENV=staging ./docker/scripts/backup.sh

stage-backup-list:
	./docker/scripts/restore.sh list staging

stage-backup-check:
	./docker/scripts/restore.sh check staging $(BACKUP)

# Rebuild, migrate and restart from whatever is already checked out — the
# deploy itself, with no git step. Split out so CI can own the git side (it
# hard-resets to origin/staging for a deterministic deploy) without pulling a
# second time mid-release, which could otherwise build one commit and migrate
# another.
stage-release: edge-network
	$(COMPOSE_STAGE) build app
	$(COMPOSE_STAGE) up -d db seaweedfs
	$(COMPOSE_STAGE) run --rm app php artisan migrate --force
	$(COMPOSE_STAGE) up -d app queue scheduler

stage-pull:
	git pull

# What you run by hand on the box. CI syncs git itself and calls
# stage-release directly. Expressed as ordered prerequisites rather than a
# recursive $(MAKE) call, which breaks under Windows make when the binary's
# path contains spaces.
stage-deploy: stage-pull stage-release
