# Hirfaty — coming soon page

Standalone static site (no build step, no dependency on the main Laravel/Inertia app). Deployed alongside `init.hirfaty.com` but served as its own nginx vhost, so `hirfaty.com` can later become its own staging/production target without touching the app deploy.

## Files

- `index.html` — markup, trilingual (fr/ar/en) via `data-i18n` attributes
- `style.css` — split-screen layout, reuses the main app's palette/fonts (terracotta/cream, Fraunces/Inter/Noto Naskh Arabic)
- `script.js` — language switcher (persists choice in `localStorage`, defaults to browser language)
- `assets/artisan.jpg` — copied from `resources/js/images/hero-main.jpg`
- `assets/logo.png` — copied from `resources/js/images/logo.png`

## Design notes

Full-height two-column split: photo panel (slow Ken Burns drift, warm vignette, film grain) beside a cream content panel (CSS-drawn zellij lattice, staggered fade-up entrance, shimmering progress bar). Collapses to stacked photo-over-content under 900px.

The zellij texture is drawn with repeating gradients rather than the 1.3 MB `zellij-pattern.png` — same look, no image weight.

Arabic follows the same rules as the main app: Noto Naskh Arabic, never italicised, and the whole grid mirrors via `dir="rtl"`.

All motion is disabled under `prefers-reduced-motion: reduce`.

There is deliberately **no contact email** on the page right now.

## Local preview

```
php -S 127.0.0.1:4321 -t coming-soon
```

or any static file server pointed at this folder.

## Deploying

Baked into the production image at `/var/www/coming-soon` (see `docker/app/Dockerfile`) and served by `docker/app/vhosts/hirfaty-{http,ssl}.conf`. Deploy with `make prod-deploy`; issue its TLS cert once with `make prod-cert-init-hirfaty`.

Nothing here is coupled to Laravel, so the folder can also be lifted onto any static host (Netlify, Vercel, Pages, S3) unchanged.
