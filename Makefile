reset:
	php artisan migrate:fresh
	php artisan db:seed
	php artisan db:seed --class=DeliveryOptionSeeder
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

COMPOSE = docker compose -f docker-compose.prod.yml
COMPOSE_STAGE = docker compose -f docker-compose.staging.yml
COMPOSE_PROXY = docker compose -f docker-compose.proxy.yml

PROD_DOMAIN ?= hirfaty.com
STAGING_DOMAIN ?= init.hirfaty.com
CERT_EMAIL ?= creati.maroc@gmail.com
EDGE_NETWORK = hirfaty-edge

.PHONY: reset edge-network \
	prod-build prod-up prod-down prod-restart prod-logs prod-ps \
	prod-migrate prod-db-reset prod-seed prod-artisan prod-key-generate \
	prod-deploy prod-backup \
	stage-build stage-up stage-down stage-restart stage-logs stage-ps \
	stage-migrate stage-db-reset stage-seed stage-artisan stage-deploy \
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
	@chmod 600 docker/proxy/htpasswd
	@echo "Wrote docker/proxy/htpasswd for user '$(USER)'. Run 'make proxy-up' to apply."

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

# Full redeploy after a `git pull`: rebuild the image, run migrations, then
# recreate app/queue/scheduler with the new image (db/seaweedfs untouched).
prod-deploy: edge-network
	git pull
	$(COMPOSE) build app
	$(COMPOSE) up -d db seaweedfs
	$(COMPOSE) run --rm app php artisan migrate --force
	$(COMPOSE) up -d app queue scheduler

# Dumps the prod `db` container to /var/backups/hirfaty and prunes dumps older
# than 14 days. Scheduled by hirfaty-backup.timer; run by hand to test or to
# take an ad-hoc backup before a risky migration.
prod-backup:
	./docker/scripts/backup-db.sh

# ── Staging (init.hirfaty.com) ──────────────────────────────────────────
# Same targets, against the staging stack. APP_ENV=staging in .env.staging
# means Laravel's destructive-command guard is off, so stage-db-reset needs
# no APP_ENV override and prompts for nothing — staging is disposable.

stage-build:
	$(COMPOSE_STAGE) build

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

stage-db-reset:
	$(COMPOSE_STAGE) run --rm app php artisan migrate:fresh --force
	$(COMPOSE_STAGE) run --rm app php artisan db:seed --class=SuperAdminSeeder --force

stage-seed:
	$(COMPOSE_STAGE) run --rm app php artisan db:seed --force

# Usage: make stage-artisan CMD="queue:restart"
stage-artisan:
	$(COMPOSE_STAGE) exec app php artisan $(CMD)

stage-deploy: edge-network
	git pull
	$(COMPOSE_STAGE) build app
	$(COMPOSE_STAGE) up -d db seaweedfs
	$(COMPOSE_STAGE) run --rm app php artisan migrate --force
	$(COMPOSE_STAGE) up -d app queue scheduler
