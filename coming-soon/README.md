# Hirfaty — coming soon page

Standalone static site (no build step, no dependency on the main Laravel/Inertia app). Meant to be deployed separately from `init.hirfaty.com` so `hirfaty.com` can later become its own staging/production target without touching this repo's app deploy.

## Files

- `index.html` — markup, trilingual (fr/ar/en) via `data-i18n` attributes
- `style.css` — styling, reuses the main app's brand palette/fonts (terracotta/cream, Fraunces/Inter/Noto Naskh Arabic)
- `script.js` — language switcher (persists choice in `localStorage`, defaults to browser language)
- `assets/logo.png` — copied from `resources/js/images/logo.png`

## Local preview

```
php -S 127.0.0.1:4321 -t coming-soon
```

or any static file server pointed at this folder.

## Deploying

Upload this folder as-is to any static host (Netlify, Vercel, GitHub Pages, S3+CloudFront, or a plain Nginx vhost) and point `hirfaty.com` DNS at it. No env vars, no build command needed.
