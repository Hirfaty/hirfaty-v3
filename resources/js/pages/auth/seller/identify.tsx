import { Form, usePage } from '@inertiajs/react';
import { BadgeCheck, Store, Truck } from 'lucide-react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/use-translation';
import heroPotter from '@/images/hero/hero-2.jpg';
import { identify } from '@/routes/sell';
import SellerAuthShell from './SellerAuthShell';

/*
 * Same boxed treatment the shopper's email field gets: `Input` is
 * underline-only by default, and a bare underline floating in an otherwise
 * empty column reads as decoration rather than something to type in. The
 * visible border moves to the wrapper so the focus ring can sit on the box.
 */
const fieldClass = 'border-b-0 pe-0 text-base text-souk-title';

const fieldBoxClass =
    'rounded-lg border border-souk-brown/30 bg-white px-4 transition-colors focus-within:border-souk-brown';

/**
 * The three things an artisan actually wants to know before handing over an
 * address. Deliberately short — this is a form with a preamble, not a landing
 * page, and a wall of benefits would push the field below the fold.
 */
const SELLING_POINTS = [
    { key: 'shop', Icon: Store },
    { key: 'delivery', Icon: Truck },
    { key: 'trust', Icon: BadgeCheck },
] as const;

/**
 * Hirfaty Seller, step 1 — the pitch and one email field.
 *
 * Identifier-first like /login: what happens next is decided server-side by
 * Auth\SellerRegistrationController::identify, which sends an address that
 * already has an account to the ordinary password screen and starts a
 * code-backed signup for one that doesn't. That is why this asks for nothing
 * but the address, and why there is no "already selling? sign in" link — the
 * field handles both.
 */
export default function SellerIdentify() {
    const { t } = useTranslation();
    const { flash } = usePage<{ flash?: { error?: string } }>().props;

    return (
        <SellerAuthShell
            image={heroPotter}
            quote={t('seller.join.quote')}
            title={t('seller.join.identify_title')}
            titleAccent={t('seller.join.identify_title_accent')}
            subtitle={t('seller.join.identify_subtitle')}
            footer={
                <ul className="grid gap-3 pb-4 sm:grid-cols-3">
                    {SELLING_POINTS.map(({ key, Icon }) => (
                        <li
                            key={key}
                            className="rounded-xl border border-souk-brown/12 bg-souk-peach/40 px-4 py-3.5"
                        >
                            <Icon
                                className="h-4 w-4 text-souk-brown"
                                strokeWidth={1.6}
                            />
                            <p className="mt-2 text-xs leading-snug text-souk-brown-deep/75">
                                {t(`seller.join.point_${key}`)}
                            </p>
                        </li>
                    ))}
                </ul>
            }
        >
            {/* Dead ends elsewhere in the flow land back here with a reason —
                an expired signup, or a session lost between two screens. */}
            {flash?.error && (
                <p
                    role="status"
                    className="mt-6 rounded-xl border border-souk-brown/20 bg-souk-peach px-4 py-3 text-sm text-souk-title"
                >
                    {flash.error}
                </p>
            )}

            <Form {...identify.form()} disableWhileProcessing className="mt-7">
                {({ processing, errors }) => (
                    <>
                        <div>
                            <label
                                htmlFor="email"
                                className="mb-1.5 block text-sm font-medium tracking-normal text-souk-title normal-case"
                            >
                                {t('common.auth.login.email_required')}
                            </label>
                            <div className={fieldBoxClass}>
                                <Input
                                    id="email"
                                    name="email"
                                    type="email"
                                    required
                                    autoFocus
                                    autoComplete="email"
                                    className={fieldClass}
                                />
                            </div>
                            <InputError
                                message={errors.email}
                                className="mt-1.5"
                            />
                        </div>

                        <p className="mt-4 text-xs leading-relaxed text-souk-brown-deep/55">
                            {t('seller.join.privacy_note')}
                        </p>

                        <button
                            type="submit"
                            disabled={processing}
                            data-test="sell-identify-button"
                            className="mt-5 inline-flex w-full items-center justify-center gap-2.5 rounded-full bg-souk-brown px-6 py-3.5 font-display text-base text-souk-cream transition-colors duration-300 hover:bg-souk-brown-deep disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {processing && <Spinner />}
                            {t('seller.join.identify_submit')}
                        </button>
                    </>
                )}
            </Form>
        </SellerAuthShell>
    );
}
