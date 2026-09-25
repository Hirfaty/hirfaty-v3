<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Sending a visitor back to the page that made them sign in.
 *
 * A guest who taps "add to cart" or the heart on a product is sent to
 * /login; once signed in they should land on that product again, not on the
 * catalogue. The target rides in the session under Laravel's own
 * `url.intended` key, so the two ways it gets set feed the same place:
 *
 *  - Laravel's `auth` middleware, when a guest opens a protected page (/cart);
 *  - remember(), when a public page sends them to /login itself (?redirect=).
 *
 * It survives the whole multi-step sign-in — email, then password or emailed
 * code or Google, or a signup with its own code screen — because the session
 * does; nothing has to be threaded through each step's form.
 *
 * Every target is reduced to a same-site path first. An `?redirect=` value is
 * attacker-controllable (anyone can post a link to /login?redirect=…), so
 * accepting an arbitrary URL would turn the login page into an open redirect.
 */
class PostLoginRedirectService
{
    /** Laravel's key: Redirector::guest() writes it, Redirector::intended() reads it. */
    public const SESSION_KEY = 'url.intended';

    /** Pages that must never be a destination — they would loop or make no sense after signing in. */
    private const EXCLUDED = ['login', 'logout', 'register', 'forgot-password', 'reset-password', 'auth', 'email', 'two-factor-challenge', 'locale'];

    /** Remember where to go once signed in. Ignores anything unsafe. */
    public function remember(Request $request, mixed $target): void
    {
        $path = $this->sanitize($request, $target);

        if ($path !== null) {
            $request->session()->put(self::SESSION_KEY, $path);
        }
    }

    /**
     * Take the remembered destination for this user, or null for "use the
     * usual landing page". Consumed even when refused, so a stale value
     * cannot resurface on a later sign-in.
     *
     * A page the user's role cannot open is refused rather than followed:
     * RoleMiddleware would only bounce them to the catalogue with an error
     * flash, which reads as a fault on a sign-in that worked.
     */
    public function pull(Request $request, User $user): ?string
    {
        $path = $this->sanitize($request, $request->session()->pull(self::SESSION_KEY));

        if ($path === null) {
            return null;
        }

        $first = explode('/', ltrim(parse_url($path, PHP_URL_PATH) ?: '/', '/'))[0];

        $allowed = match ($first) {
            'admin' => $user->isSuperAdmin(),
            'seller' => $user->isSeller(),
            default => true,
        };

        return $allowed ? $path : null;
    }

    /** Is there a destination waiting? (Without consuming it.) */
    public function pending(Request $request): bool
    {
        return $this->sanitize($request, $request->session()->get(self::SESSION_KEY)) !== null;
    }

    /**
     * A relative path with its query string, or null.
     *
     * Absolute URLs are accepted only for this site's own host (Laravel
     * stores the full URL for its own redirects); protocol-relative and
     * backslash tricks ("//evil.test", "/\evil.test") are refused.
     */
    private function sanitize(Request $request, mixed $target): ?string
    {
        if (! is_string($target) || $target === '' || strlen($target) > 2000) {
            return null;
        }

        if (preg_match('/[\x00-\x1F\x7F\\\\]/', $target)) {
            return null;
        }

        $parts = parse_url($target);

        if ($parts === false) {
            return null;
        }

        if (isset($parts['scheme']) || isset($parts['host'])) {
            if (($parts['host'] ?? null) !== $request->getHost()) {
                return null;
            }
        }

        $path = $parts['path'] ?? '/';

        if (! str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return null;
        }

        $first = explode('/', ltrim($path, '/'))[0];

        if (in_array($first, self::EXCLUDED, true)) {
            return null;
        }

        return $path.(isset($parts['query']) ? '?'.$parts['query'] : '');
    }
}
