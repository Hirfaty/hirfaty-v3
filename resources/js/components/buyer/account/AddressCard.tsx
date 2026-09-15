import { useForm } from '@inertiajs/react';
import { MapPin, Plus } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { useTranslation } from '@/hooks/use-translation';
import { EditButton, Field, FormActions, INPUT } from './form';
import type { AddressRow } from './types';

/**
 * The one address checkout pre-fills — a card on the right of the page rather
 * than another flush section, so it reads as a distinct object next to the
 * identity/password form on the left.
 *
 * Edited in place when it exists, created when it doesn't — never "add
 * another". A buyer with several saved addresses would otherwise end up with
 * ones this card can neither show nor reach, and AddressService already makes
 * a first address the default, so the create path needs no flag.
 */
export default function AddressCard({
    address,
    cities,
}: {
    address: AddressRow | null;
    cities: { id: number; name: string }[];
}) {
    const { t } = useTranslation();
    const [editing, setEditing] = useState(false);

    const form = useForm({
        label: '',
        recipient_name: '',
        recipient_phone: '',
        address_line: '',
        city_id: '',
    });

    // Re-synced from the current prop on every open — see IdentitySection for
    // why this can't just be the useForm() initial values.
    const startEditing = () => {
        form.setData({
            label: address?.label ?? '',
            recipient_name: address?.recipient_name ?? '',
            recipient_phone: address?.recipient_phone ?? '',
            address_line: address?.address_line ?? '',
            city_id: address?.city_id ? String(address.city_id) : '',
        });
        form.clearErrors();
        setEditing(true);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setEditing(false);
                toast.success(t('buyer.account.address_saved'));
            },
        };

        if (address) {
            form.patch(route('buyer.addresses.update', address.id), options);
        } else {
            form.post(route('buyer.addresses.store'), options);
        }
    };

    return (
        // No border: the white fill and a soft shadow already lift the card off
        // the paper background, and a rule around it was a third edge doing
        // the same job.
        <aside className="rounded-2xl bg-white p-6 shadow-sm shadow-souk-brown/5 lg:sticky lg:top-10">
            <div className="flex items-start justify-between gap-3">
                <div className="flex items-center gap-2.5">
                    <MapPin
                        className="h-[1.1rem] w-[1.1rem] text-souk-green"
                        strokeWidth={1.7}
                    />
                    <h2 className="font-display text-xl leading-tight text-souk-title">
                        {t('buyer.account.section_address')}
                    </h2>
                </div>
                {!editing && address && (
                    <EditButton
                        label={t('common.edit')}
                        onClick={startEditing}
                    />
                )}
            </div>

            <p className="mt-1.5 text-base leading-relaxed text-souk-brown-deep/60">
                {t('buyer.account.section_address_desc')}
            </p>

            <div className="mt-5">
                {editing ? (
                    <form onSubmit={submit} className="space-y-4">
                        <Field
                            label={t('buyer.account.field_address_label')}
                            htmlFor="label"
                            error={form.errors.label}
                            hint={t('buyer.account.field_address_label_hint')}
                        >
                            <input
                                id="label"
                                autoFocus
                                value={form.data.label}
                                onChange={(e) =>
                                    form.setData('label', e.target.value)
                                }
                                placeholder={t(
                                    'buyer.account.field_address_label_ph',
                                )}
                                className={INPUT}
                            />
                        </Field>

                        <Field
                            label={t('buyer.account.field_city')}
                            htmlFor="city_id"
                            error={form.errors.city_id}
                        >
                            <select
                                id="city_id"
                                value={form.data.city_id}
                                onChange={(e) =>
                                    form.setData('city_id', e.target.value)
                                }
                                className={INPUT}
                            >
                                <option value="">
                                    {t('buyer.account.field_city_ph')}
                                </option>
                                {cities.map((city) => (
                                    <option key={city.id} value={city.id}>
                                        {city.name}
                                    </option>
                                ))}
                            </select>
                        </Field>

                        <Field
                            label={t('buyer.account.field_address_line')}
                            htmlFor="address_line"
                            error={form.errors.address_line}
                        >
                            <textarea
                                id="address_line"
                                rows={2}
                                autoComplete="street-address"
                                value={form.data.address_line}
                                onChange={(e) =>
                                    form.setData('address_line', e.target.value)
                                }
                                placeholder={t(
                                    'buyer.account.field_address_line_ph',
                                )}
                                className={INPUT}
                            />
                        </Field>

                        <Field
                            label={t('buyer.account.field_recipient')}
                            htmlFor="recipient_name"
                            error={form.errors.recipient_name}
                        >
                            <input
                                id="recipient_name"
                                value={form.data.recipient_name}
                                onChange={(e) =>
                                    form.setData(
                                        'recipient_name',
                                        e.target.value,
                                    )
                                }
                                className={INPUT}
                            />
                        </Field>

                        <Field
                            label={t('buyer.account.field_recipient_phone')}
                            htmlFor="recipient_phone"
                            error={form.errors.recipient_phone}
                        >
                            <input
                                id="recipient_phone"
                                inputMode="tel"
                                value={form.data.recipient_phone}
                                onChange={(e) =>
                                    form.setData(
                                        'recipient_phone',
                                        e.target.value,
                                    )
                                }
                                placeholder="0612345678"
                                className={INPUT}
                            />
                        </Field>

                        <FormActions
                            submitLabel={t('buyer.account.save_address')}
                            cancelLabel={t('common.cancel')}
                            processing={form.processing}
                            onCancel={() => setEditing(false)}
                        />
                    </form>
                ) : address ? (
                    <div className="space-y-1.5 text-base">
                        {address.label && (
                            <p className="text-xs font-medium tracking-wide text-souk-green uppercase">
                                {address.label}
                            </p>
                        )}
                        {address.recipient_name && (
                            <p className="font-medium text-souk-title">
                                {address.recipient_name}
                            </p>
                        )}
                        <p className="leading-relaxed text-souk-brown-deep/80">
                            {address.address_line}
                        </p>
                        <p className="text-souk-brown-deep/60">
                            {address.city}
                        </p>
                        {address.recipient_phone && (
                            <p className="text-souk-brown-deep/50">
                                {address.recipient_phone}
                            </p>
                        )}
                    </div>
                ) : (
                    /*
                     * No dashed outline. A dotted box is the convention for
                     * "drop something here", and this is not a drop target —
                     * it is a sentence and a button. The button already reads
                     * as the thing to press, so the outline was only adding
                     * visual noise around it.
                     */
                    <div>
                        <p className="text-base leading-relaxed text-souk-brown-deep/65">
                            {t('buyer.account.no_address')}
                        </p>
                        <button
                            type="button"
                            onClick={startEditing}
                            className="mt-4 inline-flex items-center gap-2 rounded-full bg-souk-green px-5 py-2.5 text-base text-white transition-colors hover:bg-souk-green-deep"
                        >
                            <Plus className="h-4 w-4" strokeWidth={2} />
                            {t('buyer.account.add_address')}
                        </button>
                    </div>
                )}
            </div>
        </aside>
    );
}
