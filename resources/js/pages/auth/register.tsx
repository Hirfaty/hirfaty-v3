import { Form, Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    Building2,
    Hammer,
    ShoppingBag,
    User as UserIcon,
} from 'lucide-react';
import { useState } from 'react';
import VaseIcon from '@/components/home/VaseIcon';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/use-translation';
import buyerImage from '@/images/cat-jewelry.jpg';
import sellerImage from '@/images/cat-pottery.jpg';
import logo from '@/images/logo.png';
import zellij from '@/images/zellij-pattern.png';
import { login } from '@/routes';
import { store } from '@/routes/register';

type AccountKind = 'buyer' | 'seller';
type BuyerType = 'individual' | 'professional';

const locales = [
    { code: 'fr', label: 'FR' },
    { code: 'ar', label: 'AR' },
    { code: 'en', label: 'EN' },
] as const;

const fieldClass =
    'border-souk-brown/25 py-2.5 text-souk-title placeholder:text-souk-brown-deep/30 focus:border-souk-brown';

const labelClass =
    'mb-1 block text-xs tracking-normal text-souk-brown-deep/60 normal-case';

export default function Register() {
    const { t, locale } = useTranslation();

    /*
     * `?type=seller` deep-links straight into the artisan flow — the home page
     * and header both point sellers here, so honour it on first render rather
     * than flashing the buyer copy first.
     */
    const [accountKind, setAccountKind] = useState<AccountKind>(() => {
        if (typeof window === 'undefined') {
            return 'buyer';
        }

        return new URLSearchParams(window.location.search)
            .get('type')
            ?.toLowerCase() === 'seller'
            ? 'seller'
            : 'buyer';
    });

    /*
     * Buyers choose retail vs. bulk pricing at signup — there's no admin
     * verification gate on this yet (see User::isProfessionalVerified), it's
     * purely which price list and minimum order quantity apply later. Sellers
     * don't see this toggle; `role` below ignores it whenever accountKind is
     * 'seller', so leaving the choice in place if someone switches back and
     * forth is harmless.
     */
    const [buyerType, setBuyerType] = useState<BuyerType>('individual');

    const role =
        accountKind === 'seller'
            ? 'seller'
            : buyerType === 'professional'
              ? 'professional_buyer'
              : 'buyer';

    const switchLocale = (code: string) =>
        router.post(route('locale.switch', code), {}, { preserveScroll: true });

    const accountKinds = [
        { key: 'buyer' as const, Icon: ShoppingBag },
        { key: 'seller' as const, Icon: Hammer },
    ];

    const buyerTypes = [
        { key: 'individual' as const, Icon: UserIcon },
        { key: 'professional' as const, Icon: Building2 },
    ];

    return (
        <>
            <Head title={t('common.auth.register.eyebrow')} />

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

                    {/* Artwork tracks the chosen role, so the panel confirms
                        the decision the user just made on the right. */}
                    <div className="relative flex min-h-0 flex-1 items-center justify-center px-10 py-8">
                        <div className="relative h-full max-h-[24rem]">
                            <div
                                aria-hidden="true"
                                className="absolute -top-4 -right-4 h-full w-full rounded-t-full rounded-bl-2xl border-2 border-souk-cream/40"
                            />
                            <div className="relative aspect-[4/5] h-full overflow-hidden rounded-t-full rounded-bl-2xl shadow-2xl shadow-black/30">
                                <img
                                    key={accountKind}
                                    src={
                                        accountKind === 'seller'
                                            ? sellerImage
                                            : buyerImage
                                    }
                                    alt={
                                        accountKind === 'seller'
                                            ? 'A Moroccan potter at work in the workshop'
                                            : 'Handcrafted Moroccan silver jewellery'
                                    }
                                    className="h-full w-full object-cover motion-safe:animate-fade-up"
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
                            {t(`common.auth.register.${accountKind}.quote`)}
                        </p>
                    </div>
                </aside>

                {/* ── Form panel ─────────────────────────────────────── */}
                <main className="flex min-h-screen flex-col px-6 py-6 sm:px-10 lg:h-screen lg:min-h-0 lg:overflow-y-auto lg:px-14 lg:py-6">
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

                    <div className="flex flex-1 items-center justify-center py-4 lg:py-3">
                        <div className="w-full max-w-lg">
                            <p className="text-[11px] tracking-[0.2em] text-souk-brown/70 uppercase">
                                {t('common.auth.register.eyebrow')}
                            </p>
                            <h1 className="mt-3 font-display text-[clamp(1.6rem,3.6vw,2.5rem)] leading-[1.08] tracking-[-0.02em] text-souk-title">
                                {t(`common.auth.register.${accountKind}.title`)}{' '}
                                <span className="text-souk-brown italic">
                                    {t(
                                        `common.auth.register.${accountKind}.title_accent`,
                                    )}
                                </span>
                            </h1>
                            <p className="mt-2.5 text-sm leading-relaxed text-souk-brown-deep/70">
                                {t(
                                    `common.auth.register.${accountKind}.subtitle`,
                                )}
                            </p>

                            <Form
                                {...store.form()}
                                resetOnSuccess={[
                                    'password',
                                    'password_confirmation',
                                ]}
                                disableWhileProcessing
                                className="mt-6"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        {/*
                                         * The account-kind and buyer-type
                                         * pickers below are UI-only radios
                                         * (their `name`s aren't validated
                                         * fields); this hidden input carries
                                         * the actual computed role — 'buyer',
                                         * 'seller' or 'professional_buyer' —
                                         * that the backend expects.
                                         */}
                                        <input
                                            type="hidden"
                                            name="role"
                                            value={role}
                                            readOnly
                                        />

                                        {/* Account kind — a real radio group,
                                            so it is keyboard reachable and
                                            submits with the form rather than
                                            via hidden state. */}
                                        <fieldset>
                                            <legend
                                                className={`${labelClass} mb-2`}
                                            >
                                                {t(
                                                    'common.auth.register.role_question',
                                                )}
                                            </legend>
                                            <div className="grid grid-cols-2 gap-3">
                                                {accountKinds.map(
                                                    ({ key, Icon }) => {
                                                        const active =
                                                            accountKind === key;

                                                        return (
                                                            <label
                                                                key={key}
                                                                className={`mb-0 flex cursor-pointer items-center gap-3 border px-4 py-3 tracking-normal normal-case transition-colors ${
                                                                    active
                                                                        ? 'border-souk-brown bg-souk-brown/5'
                                                                        : 'border-souk-brown/20 hover:border-souk-brown/45'
                                                                }`}
                                                            >
                                                                <input
                                                                    type="radio"
                                                                    name="account_kind"
                                                                    value={key}
                                                                    checked={
                                                                        active
                                                                    }
                                                                    onChange={() =>
                                                                        setAccountKind(
                                                                            key,
                                                                        )
                                                                    }
                                                                    className="sr-only"
                                                                />
                                                                <Icon
                                                                    className={`h-5 w-5 shrink-0 ${active ? 'text-souk-brown' : 'text-souk-brown-deep/40'}`}
                                                                    strokeWidth={
                                                                        1.5
                                                                    }
                                                                />
                                                                <span className="leading-tight">
                                                                    <span
                                                                        className={`block text-sm ${active ? 'text-souk-title' : 'text-souk-brown-deep/80'}`}
                                                                    >
                                                                        {t(
                                                                            `common.auth.register.role_${key}`,
                                                                        )}
                                                                    </span>
                                                                    <span className="block text-[11px] text-souk-brown-deep/50">
                                                                        {t(
                                                                            `common.auth.register.role_${key}_hint`,
                                                                        )}
                                                                    </span>
                                                                </span>
                                                            </label>
                                                        );
                                                    },
                                                )}
                                            </div>
                                            <InputError
                                                message={errors.role}
                                                className="mt-1.5"
                                            />
                                        </fieldset>

                                        {/* Buyer-only sub-choice: retail vs.
                                            bulk pricing. Lighter than the
                                            account-kind cards above since it's
                                            a secondary decision — a flat
                                            segmented toggle, not another pair
                                            of full cards. */}
                                        {accountKind === 'buyer' && (
                                            <fieldset className="mt-4">
                                                <legend
                                                    className={`${labelClass} mb-2`}
                                                >
                                                    {t(
                                                        'common.auth.register.buyer_type_question',
                                                    )}
                                                </legend>
                                                <div className="inline-flex border border-souk-brown/25">
                                                    {buyerTypes.map(
                                                        ({ key, Icon }) => {
                                                            const active =
                                                                buyerType ===
                                                                key;

                                                            return (
                                                                <label
                                                                    key={key}
                                                                    className={`mb-0 flex cursor-pointer items-center gap-2 px-4 py-2 text-sm tracking-normal normal-case transition-colors ${
                                                                        active
                                                                            ? 'bg-souk-brown text-souk-cream'
                                                                            : 'text-souk-brown-deep/70 hover:bg-souk-brown/5'
                                                                    }`}
                                                                >
                                                                    <input
                                                                        type="radio"
                                                                        name="buyer_type"
                                                                        value={
                                                                            key
                                                                        }
                                                                        checked={
                                                                            active
                                                                        }
                                                                        onChange={() =>
                                                                            setBuyerType(
                                                                                key,
                                                                            )
                                                                        }
                                                                        className="sr-only"
                                                                    />
                                                                    <Icon
                                                                        className="h-4 w-4 shrink-0"
                                                                        strokeWidth={
                                                                            1.5
                                                                        }
                                                                    />
                                                                    {t(
                                                                        `common.auth.register.buyer_type_${key}`,
                                                                    )}
                                                                </label>
                                                            );
                                                        },
                                                    )}
                                                </div>
                                                <p className="mt-1.5 text-[11px] leading-relaxed text-souk-brown-deep/50">
                                                    {t(
                                                        `common.auth.register.buyer_type_${buyerType}_hint`,
                                                    )}
                                                </p>
                                            </fieldset>
                                        )}

                                        <div className="mt-4 grid grid-cols-2 gap-x-5 gap-y-3.5">
                                            <div>
                                                <label
                                                    htmlFor="first_name"
                                                    className={labelClass}
                                                >
                                                    {t(
                                                        'common.auth.register.first_name',
                                                    )}
                                                </label>
                                                <Input
                                                    id="first_name"
                                                    name="first_name"
                                                    type="text"
                                                    required
                                                    autoFocus
                                                    autoComplete="given-name"
                                                    className={fieldClass}
                                                />
                                                <InputError
                                                    message={errors.first_name}
                                                    className="mt-1.5"
                                                />
                                            </div>

                                            <div>
                                                <label
                                                    htmlFor="last_name"
                                                    className={labelClass}
                                                >
                                                    {t(
                                                        'common.auth.register.last_name',
                                                    )}
                                                </label>
                                                <Input
                                                    id="last_name"
                                                    name="last_name"
                                                    type="text"
                                                    required
                                                    autoComplete="family-name"
                                                    className={fieldClass}
                                                />
                                                <InputError
                                                    message={errors.last_name}
                                                    className="mt-1.5"
                                                />
                                            </div>

                                            <div className="col-span-2 sm:col-span-1">
                                                <label
                                                    htmlFor="email"
                                                    className={labelClass}
                                                >
                                                    {t(
                                                        'common.auth.register.email',
                                                    )}
                                                </label>
                                                <Input
                                                    id="email"
                                                    name="email"
                                                    type="email"
                                                    required
                                                    autoComplete="email"
                                                    placeholder={t(
                                                        'common.auth.login.email_placeholder',
                                                    )}
                                                    className={fieldClass}
                                                />
                                                <InputError
                                                    message={errors.email}
                                                    className="mt-1.5"
                                                />
                                            </div>

                                            <div className="col-span-2 sm:col-span-1">
                                                <label
                                                    htmlFor="phone_number"
                                                    className={labelClass}
                                                >
                                                    {t(
                                                        'common.auth.register.phone',
                                                    )}
                                                </label>
                                                <Input
                                                    id="phone_number"
                                                    name="phone_number"
                                                    type="tel"
                                                    required
                                                    autoComplete="tel"
                                                    placeholder="+212 6 12 34 56 78"
                                                    className={fieldClass}
                                                />
                                                <InputError
                                                    message={
                                                        errors.phone_number
                                                    }
                                                    className="mt-1.5"
                                                />
                                            </div>

                                            {/* Masked fields tolerate a narrow
                                                column, so they pair up even on
                                                a phone — email and phone do
                                                not, and stay full width. */}
                                            <div>
                                                <label
                                                    htmlFor="password"
                                                    className={labelClass}
                                                >
                                                    {t(
                                                        'common.auth.register.password',
                                                    )}
                                                </label>
                                                <PasswordInput
                                                    id="password"
                                                    name="password"
                                                    required
                                                    autoComplete="new-password"
                                                    className={fieldClass}
                                                />
                                                <InputError
                                                    message={errors.password}
                                                    className="mt-1.5"
                                                />
                                            </div>

                                            <div>
                                                <label
                                                    htmlFor="password_confirmation"
                                                    className={labelClass}
                                                >
                                                    {t(
                                                        'common.auth.register.password_confirm',
                                                    )}
                                                </label>
                                                <PasswordInput
                                                    id="password_confirmation"
                                                    name="password_confirmation"
                                                    required
                                                    autoComplete="new-password"
                                                    className={fieldClass}
                                                />
                                                <InputError
                                                    message={
                                                        errors.password_confirmation
                                                    }
                                                    className="mt-1.5"
                                                />
                                            </div>
                                        </div>

                                        {/* Production enforces the strict
                                            Password::defaults() set, so spell
                                            the rules out rather than letting
                                            people discover them by failing. */}
                                        <p className="mt-1.5 text-[11px] leading-relaxed text-souk-brown-deep/45">
                                            {t(
                                                'common.auth.register.password_hint',
                                            )}
                                        </p>

                                        <label className="mt-4 mb-0 flex cursor-pointer items-start gap-2.5 text-sm tracking-normal text-souk-brown-deep/75 normal-case select-none">
                                            <input
                                                type="checkbox"
                                                name="terms_accepted"
                                                required
                                                className="mt-0.5 h-4 w-4 shrink-0 accent-souk-brown"
                                            />
                                            <span className="leading-snug">
                                                {t(
                                                    'common.auth.register.terms_prefix',
                                                )}{' '}
                                                <Link
                                                    href="/privacy"
                                                    className="text-souk-brown underline underline-offset-4"
                                                >
                                                    {t(
                                                        'common.auth.register.terms_privacy',
                                                    )}
                                                </Link>{' '}
                                                {t(
                                                    'common.auth.register.terms_and',
                                                )}{' '}
                                                <Link
                                                    href="/terms"
                                                    className="text-souk-brown underline underline-offset-4"
                                                >
                                                    {t(
                                                        'common.auth.register.terms_conditions',
                                                    )}
                                                </Link>
                                                .
                                            </span>
                                        </label>
                                        <InputError
                                            message={errors.terms_accepted}
                                            className="mt-1.5"
                                        />

                                        <button
                                            type="submit"
                                            disabled={processing}
                                            data-test="register-user-button"
                                            className="group mt-4 inline-flex w-full items-center justify-center gap-2.5 bg-souk-brown px-6 py-3.5 font-display text-base text-souk-cream transition-colors duration-300 hover:bg-souk-brown-deep disabled:cursor-not-allowed disabled:opacity-60"
                                        >
                                            {processing && <Spinner />}
                                            {t(
                                                `common.auth.register.${accountKind}.submit`,
                                            )}
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

                            <p className="mt-4 border-t border-souk-brown/10 pt-3.5 text-center text-sm text-souk-brown-deep/65">
                                {t('common.auth.register.have_account')}{' '}
                                <Link
                                    href={login()}
                                    className="text-souk-brown underline underline-offset-4 transition-colors hover:text-souk-brown-deep"
                                >
                                    {t('common.auth.register.sign_in')}
                                </Link>
                            </p>
                        </div>
                    </div>
                </main>
            </div>
        </>
    );
}
