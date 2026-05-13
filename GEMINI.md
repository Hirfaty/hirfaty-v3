# Project Hirfaty

## Overview

Hirfaty is a modern e-commerce marketplace platform built with a robust Laravel 13 backend and a high-performance React 19 frontend. It supports a multi-role ecosystem allowing buyers, sellers, and super administrators to interact within a unified platform.

### Tech Stack

- **Backend:** [Laravel 13](https://laravel.com/) (PHP 8.3+)
- **Frontend:** [React 19](https://react.dev/) (with [React Compiler](https://react.dev/learn/react-compiler)), [TypeScript](https://www.typescriptlang.org/)
- **Inertia.js:** [Inertia.js 3](https://inertiajs.com/) for seamless SPA experience
- **Styling:** [Tailwind CSS 4](https://tailwindcss.com/), [Radix UI](https://www.radix-ui.com/) primitives
- **Authentication:** [Laravel Fortify](https://laravel.com/docs/fortify)
- **State/Routing:** [Ziggy](https://github.com/tighten/ziggy), [Wayfinder](https://github.com/laravel/wayfinder)
- **I18n:** [Laravel Localization](https://github.com/mcamara/laravel-localization) (Supporting AR, FR, EN)
- **Testing:** [Pest PHP](https://pestphp.com/)

---

## Architecture & Roles

The application implements role-based access control (RBAC) with three primary roles:

1.  **Super Admin:**
    - System-wide management.
    - User oversight and banning.
    - Product approval/rejection and archiving.
    - Order management and payout processing.
    - Coupon and review moderation.
2.  **Seller:**
    - Storefront setup and management.
    - Product inventory management.
    - Order fulfillment and status updates.
    - Earnings tracking.
3.  **Buyer:**
    - Product discovery and searching.
    - Cart and Wishlist management.
    - Checkout process.
    - Personal order history.

---

## Development Workflow

### Building and Running

- **Initial Setup:** `composer run setup`
- **Development Server:** `composer run dev` (Runs Artisan, Vite, and Queue worker concurrently)
- **Database Migrations:** `php artisan migrate --seed`
- **Production Build:** `npm run build`

### Testing

- **Run all tests:** `composer run test` (Executes Pest tests)
- **Unit Tests:** `php artisan test --testsuite=Unit`
- **Feature Tests:** `php artisan test --testsuite=Feature`

### Code Quality & Standards

- **PHP Linting:** `composer run lint` (uses Laravel Pint)
- **JS Linting:** `npm run lint` (ESLint)
- **Formatting:** `npm run format` (Prettier)
- **Type Checking:** `npm run types:check` (TypeScript)
- **CI Check:** `composer run ci:check` (Runs all linters and tests)

---

## Project Structure

- `app/Http/Controllers/`: Role-specific controllers (Admin, Buyer, Seller).
- `app/Models/`: Eloquent models with role helpers and relationships.
- `database/migrations/`: Database schema definitions.
- `resources/js/pages/`: Inertia.js React components.
- `resources/js/components/ui/`: Reusable UI components (Radix/Tailwind).
- `routes/web.php`: Primary routing definitions with role middleware.
- `lang/`: Translation files for AR, FR, and EN.

---

## Conventions

- **Routing:** Use role-based middleware (`role:super_admin`, `role:seller`, `role:buyer`).
- **State Management:** Leverage Inertia.js for server-driven state; use React hooks for local UI state.
- **Styling:** Prefer Tailwind CSS utility classes and Radix UI primitives for consistency.
- **Types:** Strictly define TypeScript interfaces for all Inertia props and shared data.
- **Localization:** Always use translation helpers (`__('key')` in PHP, or equivalent in JS) to support multi-language requirements.

# Typography & Heading Style

## Display Titles

Use `.font-display` or `.h-editorial` for major headings.

Style characteristics:

- Elegant editorial look
- Inspired by luxury magazines and artisan brands
- Tight line-height
- Slight negative letter spacing
- Warm, sophisticated feeling
- Serif typography using Fraunces

Typical usage:

- Hero titles
- Section titles
- Product storytelling
- Collection names

Example classes:

- `font-display`
- `h-editorial`
- `text-4xl md:text-6xl`
- `tracking-tight`
- `leading-none`

---

## Supporting Titles

Use sans-serif (`Inter`) for:

- Dashboard headings
- Labels
- Utility UI
- Admin interfaces

Typical classes:

- `font-sans`
- `uppercase tracking-[0.18em]`
- `text-sm`
- `font-medium`

---

## Visual Tone

The interface should feel:

- Editorial
- Handcrafted
- Warm
- Premium
- Minimal
- Moroccan artisan-inspired
- Paper-like and tactile

Avoid:

- Neon colors
- Overly futuristic UI
- Glassmorphism
- Heavy gradients
- Sharp aggressive borders
