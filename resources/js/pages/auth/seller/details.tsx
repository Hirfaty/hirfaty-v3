import { Form, Link } from '@inertiajs/react';
import { ArrowRight, Building2, Hammer, MapPin, Users } from 'lucide-react';
import type { ComponentType } from 'react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import PasswordStrengthMeter from '@/components/PasswordStrengthMeter';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { usePasswordCheck } from '@/hooks/use-password-check';
import { useTranslation } from '@/hooks/use-translation';
import heroZellige from '@/images/hero-zellige.jpg';
import { store as createAccount } from '@/routes/login/details';
import SellerAuthShell from './SellerAuthShell';

const fieldClass =
    'border-souk-brown/25 py-2.5 text-souk-title placeholder:text-souk-brown-deep/30 focus:border-souk-brown';

const labelClass =
    'mb-1 block text-xs tracking-normal text-souk-brown-deep/60 normal-case';

type Option = { id: number; name: string };

/** Mirrors Store::TYPES. */
type StoreType = 'artist' | 'company' | 'cooperative';

const TYPE_ICON: Record<StoreType, ComponentType<{ className?: string }>> = {
    artist: Hammer,
    company: Building2,
    cooperative: Users,
};

/** Kept in step with the server's `max:2500` in RegistrationController. */
const DESCRIPTION_MAX = 2500;

/**
 * Seller signup step 3 — the account and the workshop, on one form.
 *
 * Replaced a three-step wizard that ran *after* signup and let the seller skip
 * craft, city and description. Those three are exactly
 * Store::REQUIRED_PROFILE_FIELDS, and a shop missing any of them is silently
 * never filed for review — so "skip" meant a workshop that sat invisible
 * forever while its owner waited for an approval nobody had been asked for.
 * Everything here is required, and submitting it puts the shop in the queue.
 *
 * Uncontrolled throughout: Inertia's `<Form>` reads the live DOM on submit, so
 * the only local state is what the UI itself needs to render — the strength
 * meter, the selected type tile and the character count.
 */
export default function SellerDetails({
    categories,
    cities,
    types,
}: {
    categories: Option[];
    cities: Option[];
    types: StoreType[];
}) {
    const { t } = useTranslation();

    const [passwordValue, setPasswordValue] = useState('');
    const passwordCheck = usePasswordCheck();
    const [type, setType] = useState<StoreType>(types[0] ?? 'artist');
    const [descriptionLength, setDescriptionLength] = useState(0);

    return (
        <SellerAuthShell
            wide
            image={heroZellige}
            quote={t('seller.join.quote_details')}
            title={t('seller.join.details_title')}
            titleAccent={t('seller.join.details_title_accent')}
            subtitle={t('seller.join.details_subtitle')}
        >
            <Form
                {...createAccount.form()}
                onBefore={passwordCheck.onBefore}
                resetOnSuccess={['password', 'password_confirmation']}
                disableWhileProcessing
                className="mt-7"
            >
                {({ processing, errors }) => (
                    <>
                        {/* ── You ─────────────────────────────────────── */}
                        <fieldset>
                            <legend className="text-[11px] tracking-[0.18em] text-souk-brown/70 uppercase">
                                {t('seller.join.section_you')}
                            </legend>

                            <div className="mt-4 grid grid-cols-1 gap-x-4 gap-y-3.5 sm:grid-cols-2">
                                <div>
                                    <label
                                        htmlFor="first_name"
                                        className={labelClass}
                                    >
                                        {t('common.auth.login.first_name')}
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
                                        {t('common.auth.login.last_name')}
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

                                {/* Full width once the grid is two columns —
                                    a formatted international number does not
                                    fit a half column. */}
                                <div className="sm:col-span-2">
                                    <label
                                        htmlFor="phone_number"
                                        className={labelClass}
                                    >
                                        {t('common.auth.login.phone')}
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
                                        message={errors.phone_number}
                                        className="mt-1.5"
                                    />
                                </div>

                                <div>
                                    <label
                                        htmlFor="password"
                                        className={labelClass}
                                    >
                                        {t('common.auth.login.password')}
                                    </label>
                                    <PasswordInput
                                        id="password"
                                        name="password"
                                        required
                                        autoComplete="new-password"
                                        className={fieldClass}
                                        onChange={(e) => {
                                            setPasswordValue(e.target.value);
                                            passwordCheck.clearError(
                                                'password',
                                            );
                                        }}
                                    />
                                    <PasswordStrengthMeter
                                        password={passwordValue}
                                    />
                                    <InputError
                                        message={
                                            passwordCheck.errors.password ??
                                            errors.password
                                        }
                                        className="mt-1.5"
                                    />
                                </div>

                                <div>
                                    <label
                                        htmlFor="password_confirmation"
                                        className={labelClass}
                                    >
                                        {t(
                                            'common.auth.login.password_confirm',
                                        )}
                                    </label>
                                    <PasswordInput
                                        id="password_confirmation"
                                        name="password_confirmation"
                                        required
                                        autoComplete="new-password"
                                        className={fieldClass}
                                        onChange={() =>
                                            passwordCheck.clearError(
                                                'password_confirmation',
                                            )
                                        }
                                    />
                                    <InputError
                                        message={
                                            passwordCheck.errors
                                                .password_confirmation ??
                                            errors.password_confirmation
                                        }
                                        className="mt-1.5"
                                    />
                                </div>
                            </div>

                            {/* Password::defaults() enforces this everywhere,
                                so spell the rules out rather than letting
                                people discover them by failing. */}
                            <p className="mt-1.5 text-[11px] leading-relaxed text-souk-brown-deep/45">
                                {t('common.auth.login.password_hint')}
                            </p>
                        </fieldset>

                        {/* ── Your workshop ───────────────────────────── */}
                        <fieldset className="mt-8 border-t border-souk-brown/12 pt-7">
                            <legend className="text-[11px] tracking-[0.18em] text-souk-brown/70 uppercase">
                                {t('seller.join.section_shop')}
                            </legend>

                            <div className="mt-4 space-y-4">
                                <div>
                                    <label
                                        htmlFor="store_name"
                                        className={labelClass}
                                    >
                                        {t('seller.setup.identity.name_label')}
                                    </label>
                                    <Input
                                        id="store_name"
                                        name="store_name"
                                        type="text"
                                        required
                                        placeholder={t(
                                            'seller.setup.identity.name_placeholder',
                                        )}
                                        className={fieldClass}
                                    />
                                    <p className="mt-1.5 text-[11px] text-souk-brown-deep/45">
                                        {t('seller.setup.identity.name_hint')}
                                    </p>
                                    <InputError
                                        message={errors.store_name}
                                        className="mt-1.5"
                                    />
                                </div>

                                <div>
                                    <p className={labelClass}>
                                        {t('seller.setup.identity.type_label')}
                                    </p>
                                    {/*
                                     * Radios rather than a <select>: three
                                     * options with a line of explanation each,
                                     * and the explanation is what makes the
                                     * choice answerable. The real input is
                                     * visually hidden but still focusable, so
                                     * keyboard and screen-reader users get the
                                     * native radio-group behaviour.
                                     */}
                                    <div className="grid gap-2.5 sm:grid-cols-3">
                                        {types.map((option) => {
                                            const Icon = TYPE_ICON[option];
                                            const active = type === option;

                                            return (
                                                <label
                                                    key={option}
                                                    className={`flex cursor-pointer flex-col gap-2 rounded-xl border p-3.5 transition-colors ${
                                                        active
                                                            ? 'border-souk-brown bg-souk-brown/5'
                                                            : 'border-souk-brown/20 hover:border-souk-brown/45'
                                                    }`}
                                                >
                                                    <input
                                                        type="radio"
                                                        name="type"
                                                        value={option}
                                                        checked={active}
                                                        onChange={() =>
                                                            setType(option)
                                                        }
                                                        className="sr-only"
                                                    />
                                                    <Icon
                                                        className={`h-4 w-4 ${active ? 'text-souk-brown' : 'text-souk-brown-deep/40'}`}
                                                    />
                                                    <span className="leading-tight">
                                                        <span
                                                            className={`block text-sm ${active ? 'text-souk-title' : 'text-souk-brown-deep/80'}`}
                                                        >
                                                            {t(
                                                                `seller.setup.type_${option}`,
                                                            )}
                                                        </span>
                                                        <span className="mt-0.5 block text-[11px] text-souk-brown-deep/50">
                                                            {t(
                                                                `seller.setup.type_${option}_hint`,
                                                            )}
                                                        </span>
                                                    </span>
                                                </label>
                                            );
                                        })}
                                    </div>
                                    <InputError
                                        message={errors.type}
                                        className="mt-1.5"
                                    />
                                </div>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <label
                                            htmlFor="category_id"
                                            className={labelClass}
                                        >
                                            {t('seller.join.category_label')}
                                        </label>
                                        <SelectField
                                            id="category_id"
                                            name="category_id"
                                            Icon={Hammer}
                                            options={categories}
                                            placeholder={t(
                                                'seller.join.category_placeholder',
                                            )}
                                        />
                                        <InputError
                                            message={errors.category_id}
                                            className="mt-1.5"
                                        />
                                    </div>

                                    <div>
                                        <label
                                            htmlFor="city_id"
                                            className={labelClass}
                                        >
                                            {t('seller.join.city_label')}
                                        </label>
                                        <SelectField
                                            id="city_id"
                                            name="city_id"
                                            Icon={MapPin}
                                            options={cities}
                                            placeholder={t(
                                                'seller.join.city_placeholder',
                                            )}
                                        />
                                        <InputError
                                            message={errors.city_id}
                                            className="mt-1.5"
                                        />
                                    </div>
                                </div>

                                <div>
                                    <label
                                        htmlFor="description"
                                        className={labelClass}
                                    >
                                        {t('seller.join.description_label')}
                                    </label>
                                    <textarea
                                        id="description"
                                        name="description"
                                        rows={5}
                                        required
                                        maxLength={DESCRIPTION_MAX}
                                        onChange={(e) =>
                                            setDescriptionLength(
                                                e.target.value.length,
                                            )
                                        }
                                        placeholder={t(
                                            'seller.setup.story.placeholder',
                                        )}
                                        className="w-full resize-y rounded-lg border border-souk-brown/25 bg-white px-4 py-3 text-sm leading-relaxed text-souk-title placeholder:text-souk-brown-deep/30 focus:border-souk-brown focus:ring-2 focus:ring-souk-brown/10 focus:outline-none"
                                    />
                                    <div className="mt-1.5 flex items-start justify-between gap-4">
                                        <p className="text-[11px] text-souk-brown-deep/45">
                                            {t('seller.join.description_hint')}
                                        </p>
                                        <span className="shrink-0 text-[11px] text-souk-brown-deep/45 tabular-nums">
                                            {descriptionLength}/
                                            {DESCRIPTION_MAX}
                                        </span>
                                    </div>
                                    <InputError
                                        message={errors.description}
                                        className="mt-1.5"
                                    />
                                </div>
                            </div>
                        </fieldset>

                        <label className="mt-6 mb-0 flex cursor-pointer items-start gap-2.5 text-sm tracking-normal text-souk-brown-deep/75 normal-case select-none">
                            <input
                                type="checkbox"
                                name="terms_accepted"
                                required
                                className="mt-0.5 h-4 w-4 shrink-0 accent-souk-brown"
                            />
                            <span className="leading-snug">
                                {t('common.auth.login.terms_prefix')}{' '}
                                <Link
                                    href={route('privacy')}
                                    className="text-souk-brown underline underline-offset-4"
                                >
                                    {t('common.auth.login.terms_privacy')}
                                </Link>{' '}
                                {t('common.auth.login.terms_and')}{' '}
                                <Link
                                    href={route('terms')}
                                    className="text-souk-brown underline underline-offset-4"
                                >
                                    {t('common.auth.login.terms_conditions')}
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
                            data-test="sell-details-button"
                            className="group mt-6 inline-flex w-full items-center justify-center gap-2.5 rounded-xl bg-souk-brown px-6 py-3.5 font-display text-base text-souk-cream transition-colors duration-300 hover:bg-souk-brown-deep disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {processing && <Spinner />}
                            {t('seller.join.details_submit')}
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
        </SellerAuthShell>
    );
}

/**
 * A native <select> with an icon in the gutter.
 *
 * Uncontrolled, unlike the one the old wizard used: `<Form>` reads the DOM on
 * submit, so holding the value in React state would buy nothing and add a
 * render per keystroke. The empty first option is `required`-friendly — it has
 * no value, so the browser blocks submission until something real is picked.
 */
function SelectField({
    id,
    name,
    Icon,
    options,
    placeholder,
}: {
    id: string;
    name: string;
    Icon: ComponentType<{ className?: string; strokeWidth?: number }>;
    options: Option[];
    placeholder: string;
}) {
    return (
        <div className="relative">
            <Icon
                className="pointer-events-none absolute start-4 top-1/2 h-4 w-4 -translate-y-1/2 text-souk-brown-deep/35"
                strokeWidth={1.6}
            />
            <select
                id={id}
                name={name}
                required
                defaultValue=""
                className="w-full appearance-none rounded-lg border border-souk-brown/25 bg-white py-2.5 ps-11 pe-10 text-sm text-souk-title focus:border-souk-brown focus:ring-2 focus:ring-souk-brown/10 focus:outline-none"
            >
                <option value="" disabled>
                    {placeholder}
                </option>
                {options.map((o) => (
                    <option key={o.id} value={o.id}>
                        {o.name}
                    </option>
                ))}
            </select>
            <span
                aria-hidden="true"
                className="pointer-events-none absolute end-4 top-1/2 -translate-y-1/2 text-souk-brown-deep/40"
            >
                ▾
            </span>
        </div>
    );
}
