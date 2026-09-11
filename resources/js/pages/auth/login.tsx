import { Form, Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Check, Lock } from 'lucide-react';
import VaseIcon from '@/components/home/VaseIcon';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/use-translation';
import heroMain from '@/images/hero-main.jpg';
import logo from '@/images/logo.png';
import zellij from '@/images/zellij-pattern.png';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

const locales = [
    { code: 'fr', label: 'FR' },
    { code: 'ar', label: 'AR' },
    { code: 'en', label: 'EN' },
] as const;

type Props = {
    status?: string;
    canResetPassword?: boolean;
    canRegister?: boolean;
};

export default function Login({
    status,
    canResetPassword = true,
    canRegister = true,
}: Props) {
    const { t, locale } = useTranslation();

    const switchLocale = (code: string) =>
        router.post(route('locale.switch', code), {}, { preserveScroll: true });

    return (
        <>
            <Head title={t('common.auth.login.submit')} />

            {/*
             * `lg:h-screen` (not min-h) so the whole screen fits the viewport
             * with no page scroll; each column then manages its own overflow.
             */}
            <div className="min-h-screen bg-souk-paper lg:grid lg:h-screen lg:grid-cols-[0.9fr_1.1fr]">
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
                            href="/"
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
                     * The arch image absorbs whatever height is left over:
                     * `flex-1 min-h-0` lets it shrink on short screens, and the
                     * aspect-ratio box takes its width from that height so the
                     * panel never pushes the page past one viewport.
                     */}
                    <div className="relative flex min-h-0 flex-1 items-center justify-center px-10 py-8">
                        <div className="relative h-full max-h-[26rem]">
                            <div
                                aria-hidden="true"
                                className="absolute -top-4 -right-4 h-full w-full rounded-t-full rounded-bl-2xl border-2 border-souk-cream/40"
                            />
                            <div className="relative aspect-[4/5] h-full overflow-hidden rounded-t-full rounded-bl-2xl shadow-2xl shadow-black/30">
                                <img
                                    src={heroMain}
                                    alt="A Moroccan artisan shaping a clay vessel by hand"
                                    className="h-full w-full object-cover"
                                />
                                <div
                                    aria-hidden="true"
                                    className="absolute inset-0 bg-gradient-to-t from-souk-brown/50 via-transparent to-transparent"
                                />
                            </div>
                        </div>
                    </div>

                    <div className="relative shrink-0 px-10 pb-10">
                        <VaseIcon className="h-8 w-8 text-souk-cream/45" />
                        <p className="mt-4 max-w-md font-display text-xl leading-snug text-souk-cream">
                            {t('common.auth.login.quote')}
                        </p>

                        {/* <dl className="mt-6 flex gap-10 border-t border-souk-cream/20 pt-5">
                            <div>
                                <dt className="font-display text-xl text-white">
                                    120+
                                </dt>
                                <dd className="mt-0.5 text-xs text-souk-cream/65">
                                    {t('common.auth.login.trust_artisans')}
                                </dd>
                            </div>
                            <div>
                                <dt className="font-display text-xl text-white">
                                    14
                                </dt>
                                <dd className="mt-0.5 text-xs text-souk-cream/65">
                                    {t('common.auth.login.trust_cities')}
                                </dd>
                            </div>
                        </dl> */}
                    </div>
                </aside>

                {/* ── Form panel ─────────────────────────────────────── */}
                <main className="flex min-h-screen flex-col px-6 py-6 sm:px-10 lg:h-screen lg:min-h-0 lg:overflow-y-auto lg:px-14 lg:py-8">
                    {/* top bar: back link (+ mobile logo) and locale switcher */}
                    <div className="flex items-center justify-between gap-4">
                        <Link
                            href="/"
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

                    {/* the form, optically centred in the remaining space */}
                    <div className="flex flex-1 items-center justify-center py-6 lg:py-3">
                        <div className="w-full max-w-sm">
                            <p className="text-[11px] tracking-[0.2em] text-souk-brown/70 uppercase">
                                {t('common.auth.login.eyebrow')}
                            </p>
                            <h1 className="mt-3 font-display text-[clamp(1.9rem,4vw,2.75rem)] leading-[1.08] tracking-[-0.02em] text-souk-title">
                                {t('common.auth.login.title')}{' '}
                                <span className="text-souk-brown italic">
                                    {t('common.auth.login.title_accent')}
                                </span>
                            </h1>
                            <p className="mt-3 text-sm leading-relaxed text-souk-brown-deep/70">
                                {t('common.auth.login.subtitle')}
                            </p>

                            {status && (
                                <div
                                    role="status"
                                    className="mt-6 flex items-start gap-2.5 border-s-2 border-emerald-600/50 bg-emerald-50/70 px-3.5 py-2.5 text-sm text-emerald-800"
                                >
                                    <Check
                                        className="mt-0.5 h-4 w-4 shrink-0"
                                        strokeWidth={2}
                                    />
                                    {status}
                                </div>
                            )}

                            {/*
                             * Fields intentionally keep the project's own
                             * underline `Input` styling (no box, no radius) —
                             * only the palette is retuned. Labels are sentence
                             * case, so each one has to undo the global
                             * uppercase `label` rule from app.css.
                             */}
                            <Form
                                {...store.form()}
                                resetOnSuccess={['password']}
                                className="mt-8 space-y-5"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div>
                                            <label
                                                htmlFor="email"
                                                className="mb-1 block text-xs tracking-normal text-souk-brown-deep/60 normal-case"
                                            >
                                                {t('common.auth.login.email')}
                                            </label>
                                            <Input
                                                id="email"
                                                name="email"
                                                type="email"
                                                required
                                                autoFocus
                                                autoComplete="email"
                                                placeholder={t(
                                                    'common.auth.login.email_placeholder',
                                                )}
                                                className="border-souk-brown/25 py-2.5 text-souk-title placeholder:text-souk-brown-deep/30 focus:border-souk-brown"
                                            />
                                            <InputError
                                                message={errors.email}
                                                className="mt-1.5"
                                            />
                                        </div>

                                        <div>
                                            <div className="mb-1 flex items-baseline justify-between gap-3">
                                                <label
                                                    htmlFor="password"
                                                    className="mb-0 block text-xs tracking-normal text-souk-brown-deep/60 normal-case"
                                                >
                                                    {t(
                                                        'common.auth.login.password',
                                                    )}
                                                </label>
                                                {canResetPassword && (
                                                    <Link
                                                        href={request()}
                                                        className="text-xs text-souk-brown/80 transition-colors hover:text-souk-brown"
                                                    >
                                                        {t(
                                                            'common.auth.login.forgot',
                                                        )}
                                                    </Link>
                                                )}
                                            </div>
                                            <PasswordInput
                                                id="password"
                                                name="password"
                                                required
                                                autoComplete="current-password"
                                                placeholder={t(
                                                    'common.auth.login.password_placeholder',
                                                )}
                                                className="border-souk-brown/25 py-2.5 text-souk-title placeholder:text-souk-brown-deep/30 focus:border-souk-brown"
                                            />
                                            <InputError
                                                message={errors.password}
                                                className="mt-1.5"
                                            />
                                        </div>

                                        {/*
                                         * `name` matters: without it the value
                                         * never reaches Fortify and "remember
                                         * me" silently does nothing.
                                         */}
                                        <label className="mb-4 flex w-fit cursor-pointer items-center gap-2.5 pt-1 text-sm tracking-normal text-souk-brown-deep/75 normal-case select-none">
                                            <input
                                                type="checkbox"
                                                name="remember"
                                                defaultChecked
                                                className="h-4 w-4 accent-souk-brown"
                                            />
                                            {t('common.auth.login.remember')}
                                        </label>

                                        <button
                                            type="submit"
                                            disabled={processing}
                                            data-test="login-button"
                                            className="group inline-flex w-full cursor-pointer items-center justify-center gap-2.5 rounded-xl bg-souk-brown px-6 py-3.5 font-display text-base text-souk-cream transition-colors duration-300 hover:bg-souk-brown-deep disabled:cursor-not-allowed disabled:opacity-60"
                                        >
                                            {processing && <Spinner />}
                                            {t('common.auth.login.submit')}
                                            {!processing && (
                                                <ArrowRight
                                                    className="h-4 w-4 transition-transform duration-300 group-hover:translate-x-1 rtl:rotate-180"
                                                    strokeWidth={1.75}
                                                />
                                            )}
                                        </button>
                                    </>
                                )}
                            </Form>

                            {canRegister && (
                                <p className="mt-6 border-t border-souk-brown/10 pt-5 text-center text-sm text-souk-brown-deep/65">
                                    {t('common.auth.login.no_account')}{' '}
                                    <Link
                                        href={register()}
                                        className="text-souk-brown underline underline-offset-4 transition-colors hover:text-souk-brown-deep"
                                    >
                                        {t('common.auth.login.create_account')}
                                    </Link>
                                </p>
                            )}

                            <p className="mt-5 flex items-center justify-center gap-1.5 text-[11px] text-souk-brown-deep/40">
                                <Lock className="h-3 w-3" strokeWidth={1.5} />
                                {t('common.auth.login.trust_secure')}
                            </p>
                        </div>
                    </div>
                </main>
            </div>
        </>
    );
}
