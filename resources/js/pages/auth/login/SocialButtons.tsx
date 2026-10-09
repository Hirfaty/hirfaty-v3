import { useTranslation } from '@/hooks/use-translation';

export type SocialProvider = 'google' | 'facebook';

/**
 * Brand marks, inlined as SVG rather than pulled from lucide.
 *
 * lucide has no Google or Facebook icon — they are trademarks, not UI symbols,
 * and both companies require their own mark be used unmodified. `currentColor`
 * is deliberately *not* used: Google's G is four fixed colours and Facebook's
 * mark has a fixed blue, and recolouring either to match the page would breach
 * the brand guidelines these buttons have to follow.
 */
export const SOCIAL_MARKS: Record<SocialProvider, React.ReactNode> = {
    google: (
        <svg
            viewBox="0 0 18 18"
            className="h-[18px] w-[18px]"
            aria-hidden="true"
        >
            <path
                fill="#4285F4"
                d="M17.64 9.2c0-.64-.06-1.25-.16-1.84H9v3.48h4.84a4.14 4.14 0 0 1-1.8 2.72v2.26h2.92c1.7-1.57 2.68-3.88 2.68-6.62Z"
            />
            <path
                fill="#34A853"
                d="M9 18c2.43 0 4.47-.8 5.96-2.18l-2.92-2.26c-.8.54-1.84.86-3.04.86-2.34 0-4.32-1.58-5.03-3.7H.96v2.33A9 9 0 0 0 9 18Z"
            />
            <path
                fill="#FBBC05"
                d="M3.97 10.72a5.4 5.4 0 0 1 0-3.44V4.95H.96a9 9 0 0 0 0 8.1l3.01-2.33Z"
            />
            <path
                fill="#EA4335"
                d="M9 3.58c1.32 0 2.5.45 3.44 1.35l2.58-2.59C13.46.89 11.43 0 9 0A9 9 0 0 0 .96 4.95l3.01 2.33C4.68 5.16 6.66 3.58 9 3.58Z"
            />
        </svg>
    ),
    facebook: (
        <svg
            viewBox="0 0 24 24"
            className="h-[18px] w-[18px]"
            aria-hidden="true"
        >
            <path
                fill="#1877F2"
                d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073Z"
            />
        </svg>
    ),
};

/**
 * "Continue with Google / Facebook", under the email field on /login.
 *
 * Renders nothing at all when no provider is configured — including the
 * divider, which would otherwise sit above an empty space on a fresh checkout
 * with no OAuth credentials in .env. The list comes from the server
 * (SocialAuthService::configuredProviders) rather than being hardcoded here,
 * so a button can never point at a route that 404s.
 *
 * Plain `<a>`, not an Inertia `<Link>`: these leave the SPA for Google's or
 * Facebook's domain, and Inertia would try to fetch the response as a page
 * visit and choke on the cross-origin redirect.
 */
export default function SocialButtons({
    providers,
}: {
    providers: SocialProvider[];
}) {
    const { t } = useTranslation();

    if (providers.length === 0) {
        return null;
    }

    return (
        <>
            {/* Rule with the word sitting in it. `aria-hidden` on the lines so
                a screen reader hears only "or", not two anonymous separators. */}
            <div className="mt-6 flex items-center gap-3">
                <span aria-hidden className="h-px flex-1 bg-souk-brown/15" />
                <span className="text-xs text-souk-brown-deep/50">
                    {t('common.auth.login.social_divider')}
                </span>
                <span aria-hidden className="h-px flex-1 bg-souk-brown/15" />
            </div>

            <div className="mt-4 grid gap-2.5">
                {providers.map((provider) => (
                    <a
                        key={provider}
                        href={route('social.redirect', { provider })}
                        data-test={`social-${provider}`}
                        className="inline-flex w-full items-center justify-center gap-3 rounded-full border border-souk-brown/25 bg-white px-6 py-3 text-sm text-souk-title transition-colors hover:border-souk-brown/50 hover:bg-souk-brown/5"
                    >
                        {SOCIAL_MARKS[provider]}
                        {t(`common.auth.login.social_${provider}`)}
                    </a>
                ))}
            </div>
        </>
    );
}
