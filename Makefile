reset:
	php artisan migrate:fresh
	php artisan db:seed
	php artisan db:seed --class=DeliveryOptionSeeder
	php artisan db:seed --class=BuyerCartSeeder
	php artisan db:seed --class=SuperAdminSeeder

# ── Production (Debian VPS) ─────────────────────────────────────────────
# Everything below drives docker-compose.prod.yml: app (nginx + php-fpm),
# queue worker, scheduler, Postgres, SeaweedFS, and a certbot helper.
# One-time host setup: install Docker, then
#   sudo cp docker/systemd/hirfaty.service /etc/systemd/system/hirfaty.service
#   sudo systemctl daemon-reload && sudo systemctl enable hirfaty
# so the stack survives a reboot; day-to-day use is `make prod-*`.

COMPOSE = docker compose -f docker-compose.prod.yml
DOMAIN ?= hirfaty.com
CERT_EMAIL ?= creati.maroc@gmail.com

.PHONY: reset prod-build prod-up prod-down prod-restart prod-logs prod-ps \
	prod-migrate prod-artisan prod-key-generate prod-deploy \
	prod-cert-init prod-cert-renew

prod-build:
	$(COMPOSE) build

prod-up:
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
prod-deploy:
	git pull
	$(COMPOSE) build app
	$(COMPOSE) up -d db seaweedfs
	$(COMPOSE) run --rm app php artisan migrate --force
	$(COMPOSE) up -d app queue scheduler

# One-time TLS bootstrap: app must already be serving HTTP (`make prod-up`)
# so certbot can complete the HTTP-01 challenge via the shared webroot.
prod-cert-init:
	$(COMPOSE) run --rm certbot certonly --webroot -w /var/www/certbot \
		-d $(DOMAIN) -d www.$(DOMAIN) \
		--email $(CERT_EMAIL) --agree-tos --no-eff-email
	$(COMPOSE) restart app

prod-cert-renew:
	$(COMPOSE) run --rm certbot renew
	$(COMPOSE) exec app nginx -s reload

