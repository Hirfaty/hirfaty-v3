import { Form, Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Check, LogOut, Mail } from 'lucide-react';
import VaseIcon from '@/components/home/VaseIcon';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/use-translation';
import logo from '@/images/logo.png';
import zellij from '@/images/zellij-pattern.jpg';
import { switchLocale } from '@/lib/locale';
import { logout } from '@/routes';
import { send } from '@/routes/verification';
import type { PageProps } from '@/types';

const locales = [
    { code: 'fr', label: 'FR' },
    { code: 'ar', label: 'AR' },
    { code: 'en', label: 'EN' },
] as const;

export default function VerifyEmail({ status }: { status?: string }) {
    const { t, locale } = useTranslation();
    const { auth } = usePage<PageProps>().props;

    const isLinkSent = status === 'verification-link-sent';

    return (
        <>
            <Head title={t('common.auth.verify.eyebrow')} />

            <div className="min-h-screen bg-souk-paper lg:grid lg:h-screen lg:grid-cols-[0.85fr_1.15fr]">
                {/* ── Brand panel (desktop only) ─────────────────────── */}
                <aside className="relative hidden overflow-hidden bg-souk-brown lg:flex lg:h-screen lg:flex-col">
                    <div
                        aria-hidden="true"
                        className="pointer-events-none absolute inset-0 opacity-[0.08] mix-blend-screen"
                        style={{
                            backgroundImage: `url(${zellij})`,
                            backgroundSize: '320px',
                        }}
                    />

                    <div className="relative shrink-0 px-10 pt-10">
                        <Link
                            href={route('home')}
                            className="inline-flex items-center gap-3"
                            aria-label="Hirfaty"
                        >
                            <img
                                src={logo}
                                alt=""
                                className="h-8 w-8 object-contain"
                            />
                            <span className="font-display text-xl text-white">
                                Hirfaty
                                <span className="text-souk-cream">.</span>
                            </span>
                        </Link>
                    </div>

                    {/*
                     * No photograph here on purpose — this is a transactional
                     * waiting screen, not an editorial moment, so the arch
                     * holds a large icon instead of the artisan photography
                     * login/register use. Same silhouette keeps it part of
                     * the same visual family.
                     */}
                    <div className="relative flex min-h-0 flex-1 items-center justify-center px-10 py-8">
                        <div className="relative h-full max-h-[24rem]">
                            <div
                                aria-hidden="true"
                                className="absolute -top-4 -right-4 h-full w-full rounded-t-full rounded-bl-2xl border-2 border-souk-cream/40"
                            />
                            <div className="relative flex aspect-[4/5] h-full items-center justify-center overflow-hidden rounded-t-full rounded-bl-2xl bg-souk-cream/10 shadow-2xl shadow-black/30">
                                <span
                                    aria-hidden="true"
                                    className="absolute h-32 w-32 rounded-full bg-souk-cream/10 motion-safe:animate-pulse"
                                />
                                <Mail
                                    className="relative h-20 w-20 text-souk-cream/80"
                                    strokeWidth={1.25}
                                />
                            </div>
                        </div>
                    </div>

                    <div className="relative shrink-0 px-10 pb-10">
                        <VaseIcon className="h-8 w-8 text-souk-cream/45" />
                        <p className="mt-4 max-w-md font-display text-xl leading-snug text-souk-cream">
                            {t('common.auth.verify.quote')}
                        </p>
                    </div>
                </aside>

                {/* ── Content panel ────────────────────────────────────── */}
                <main className="flex min-h-screen flex-col px-6 py-6 sm:px-10 lg:h-screen lg:min-h-0 lg:overflow-y-auto lg:px-14 lg:py-6">
                    <div className="flex items-center justify-between gap-4">
                        <Link
                            href={route('home')}
                            className="group inline-flex items-center gap-2 text-sm text-souk-brown-deep/70 transition-colors hover:text-souk-brown"
                        >
                            <ArrowLeft
                                className="h-4 w-4 transition-transform group-hover:-translate-x-0.5 rtl:rotate-180"
                                strokeWidth={1.5}
                            />
                            <span className="hidden sm:inline">
                                {t('common.auth.login.back_home')}
                            </span>
                            <span className="inline font-display text-lg text-souk-title sm:hidden">
                                Hirfaty
                            </span>
                        </Link>

                        <div className="flex items-center text-[11px] font-medium tracking-[0.18em] text-souk-brown-deep/50">
                            {locales.map((l) => (
                                <button
                                    key={l.code}
                                    type="button"
                                    onClick={() => switchLocale(l.code)}
                                    className={`px-2 py-1 transition-colors ${
                                        locale === l.code
                                            ? 'text-souk-brown'
                                            : 'hover:text-souk-brown-deep'
                                    }`}
                                >
                                    {l.label}
                                </button>
                            ))}
                        </div>
                    </div>

                    <div className="flex flex-1 items-center justify-center py-6 lg:py-3">
                        <div className="w-full max-w-sm">
                            <p className="text-[11px] tracking-[0.2em] text-souk-brown/70 uppercase">
                                {t('common.auth.verify.eyebrow')}
                            </p>
                            <h1 className="mt-3 font-display text-[clamp(1.9rem,4vw,2.75rem)] leading-[1.08] tracking-[-0.02em] text-souk-title">
                                {t('common.auth.verify.title')}{' '}
                                <span className="text-souk-brown italic">
                                    {t('common.auth.verify.title_accent')}
                                </span>
                            </h1>
                            <p className="mt-3 text-sm leading-relaxed text-souk-brown-deep/70">
                                {t('common.auth.verify.subtitle')}
                            </p>
                            {auth.user?.email && (
                                <p className="mt-1 text-sm font-medium text-souk-title">
                                    {auth.user.email}
                                </p>
                            )}

                            {isLinkSent && (
                                <div
                                    role="status"
                                    className="mt-6 flex items-start gap-2.5 border-s-2 border-emerald-600/50 bg-emerald-50/70 px-3.5 py-2.5 text-sm text-emerald-800"
                                >
                                    <Check
                                        className="mt-0.5 h-4 w-4 shrink-0"
                                        strokeWidth={2}
                                    />
                                    {t('common.auth.verify.sent_banner')}
                                </div>
                            )}

                            <Form
                                {...send.form()}
                                disableWhileProcessing
                                className="mt-6"
                            >
                                {({ processing }) => (
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="group inline-flex w-full items-center justify-center gap-2.5 rounded-xl bg-souk-brown px-6 py-3.5 font-display text-base text-souk-cream transition-colors duration-300 hover:bg-souk-brown-deep disabled:cursor-not-allowed disabled:opacity-60"
                                    >
                                        {processing ? (
                                            <Spinner />
                                        ) : (
                                            <Mail
                                                className="h-4 w-4"
                                                strokeWidth={1.75}
                                            />
                                        )}
                                        {t('common.auth.verify.resend')}
                                    </button>
                                )}
                            </Form>

                            <div className="mt-5 flex items-center justify-between gap-4 border-t border-souk-brown/10 pt-4">
                                <p className="text-xs text-souk-brown-deep/50">
                                    {t('common.auth.verify.spam_hint')}
                                </p>

                                <Link
                                    href={logout()}
                                    method="post"
                                    as="button"
                                    className="group inline-flex shrink-0 items-center gap-1.5 text-sm text-souk-brown-deep/70 transition-colors hover:text-souk-brown"
                                >
                                    <LogOut
                                        className="h-3.5 w-3.5 transition-transform group-hover:-translate-x-0.5 rtl:rotate-180"
                                        strokeWidth={1.5}
                                    />
                                    {t('common.auth.verify.logout')}
                                </Link>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        </>
    );
}
