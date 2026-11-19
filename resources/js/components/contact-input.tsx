import { AtSign, Mail, TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import CountryFlag from '@/components/country-flag';
import InputError from '@/components/input-error';
import { useTranslation } from '@/hooks/use-translation';
import {
    contactKind,
    countryName,
    looksLikeEmail,
    readPhone,
    suggestEmail,
} from '@/lib/contact';
import { cn } from '@/lib/utils';

/**
 * One field for an email address *or* a phone number — sign-in, the artisan
 * door and the contact box at checkout all use it.
 *
 * It reads what is typed as it is typed, to catch a wrong contact before a
 * code is sent to it:
 *
 *  - a number shows its country's flag the moment the code is recognisable
 *    (`06…` → Morocco, `+33…` → France), and once complete, the number
 *    written out in full — so a wrong country or a missing digit shows;
 *  - an address with a near-miss domain offers the fix (`gmial.com` →
 *    `gmail.com`), one tap to apply.
 *
 * The value is posted as typed, under `name`: the server normalises it
 * (App\Support\Contact) and has the last word.
 */
export default function ContactInput({
    id,
    name,
    value: controlled,
    onChange,
    defaultValue = '',
    error,
    autoFocus,
    disabled,
    required = true,
    boxClassName,
    inputClassName,
}: {
    id: string;
    name: string;
    /** Controlled value; leave out to let the field keep its own. */
    value?: string;
    onChange?: (value: string) => void;
    defaultValue?: string;
    error?: string;
    autoFocus?: boolean;
    disabled?: boolean;
    required?: boolean;
    /** The visible box around the adornment and the input. */
    boxClassName?: string;
    inputClassName?: string;
}) {
    const { t, locale } = useTranslation();
    const [own, setOwn] = useState(defaultValue);
    const [touched, setTouched] = useState(false);

    const value = controlled ?? own;

    const set = (next: string) => {
        setOwn(next);
        onChange?.(next);
    };

    const kind = contactKind(value);
    const phone = kind === 'phone' ? readPhone(value) : null;
    const suggestion = kind === 'email' ? suggestEmail(value) : null;

    const country = phone?.country;
    const place = country ? countryName(country, String(locale)) : undefined;

    // A number is only called wrong once it has had a fair chance: enough
    // digits to judge, or the visitor has left the field.
    const phoneWrong =
        !!phone && !phone.valid && (!phone.incomplete || touched);

    return (
        <div>
            <div
                className={cn('flex items-center gap-2.5', boxClassName)}
                // A number and an address read left to right, even in Arabic.
                dir="ltr"
            >
                {kind === 'phone' ? (
                    <CountryFlag
                        country={country}
                        title={
                            place ?? t('common.auth.contact.country_unknown')
                        }
                    />
                ) : kind === 'email' ? (
                    <Mail
                        aria-hidden
                        className="h-4 w-4 shrink-0 text-souk-brown/60"
                        strokeWidth={1.6}
                    />
                ) : (
                    <AtSign
                        aria-hidden
                        className="h-4 w-4 shrink-0 text-souk-brown/40"
                        strokeWidth={1.6}
                    />
                )}

                <input
                    id={id}
                    name={name}
                    type="text"
                    required={required}
                    autoFocus={autoFocus}
                    disabled={disabled}
                    // "username": password managers and the browser offer the
                    // address or number the visitor signed in with before.
                    autoComplete="username"
                    autoCapitalize="none"
                    autoCorrect="off"
                    spellCheck={false}
                    dir="ltr"
                    value={value}
                    onChange={(e) => set(e.target.value)}
                    onBlur={() => setTouched(true)}
                    placeholder={t('common.auth.contact.placeholder')}
                    aria-invalid={phoneWrong || !!error || undefined}
                    className={cn(
                        'min-w-0 flex-1 bg-transparent py-3 text-base text-souk-title outline-none placeholder:text-souk-brown-deep/30',
                        inputClassName,
                    )}
                />
            </div>

            {/* What the visitor is about to rely on, spelled out. */}
            <div className="mt-1.5 min-h-[1.25rem] text-xs leading-snug">
                {phoneWrong ? (
                    <p className="flex items-start gap-1.5 text-terracotta">
                        <TriangleAlert
                            className="mt-px h-3.5 w-3.5 shrink-0"
                            strokeWidth={1.6}
                        />
                        <span>
                            {t('common.auth.contact.phone_incomplete')}
                            {place && ` (${place})`}
                        </span>
                    </p>
                ) : phone?.valid ? (
                    <p className="text-souk-brown-deep/60">
                        {t('common.auth.contact.will_sms')}{' '}
                        <bdi dir="ltr" className="font-medium text-souk-title">
                            {phone.international}
                        </bdi>
                        {place && ` · ${place}`}
                    </p>
                ) : phone && place ? (
                    <p className="text-souk-brown-deep/45">{place}</p>
                ) : suggestion ? (
                    <p className="text-souk-brown-deep/70">
                        {t('common.auth.contact.suggest')}{' '}
                        <button
                            type="button"
                            onClick={() => set(suggestion)}
                            className="font-medium text-souk-brown underline underline-offset-4 hover:text-souk-brown-deep"
                        >
                            <bdi dir="ltr">{suggestion}</bdi>
                        </button>{' '}
                        ?
                    </p>
                ) : kind === 'email' && looksLikeEmail(value) ? (
                    <p className="text-souk-brown-deep/60">
                        {t('common.auth.contact.will_email')}{' '}
                        <bdi dir="ltr" className="font-medium text-souk-title">
                            {value.trim().toLowerCase()}
                        </bdi>
                    </p>
                ) : null}

                <InputError message={error} className="mt-1" />
            </div>
        </div>
    );
}
