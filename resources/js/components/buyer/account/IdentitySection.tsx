import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import { useTranslation } from '@/hooks/use-translation';
import {
    EditButton,
    Field,
    FormActions,
    INPUT,
    LockedField,
    Section,
    ViewRow,
} from './form';
import type { Buyer } from './types';

/**
 * Name, shown as text until the pencil is clicked, then editable. Email and
 * phone stay locked either way.
 *
 * The lock is enforced server-side too — Buyer\ProfileUpdateRequest whitelists
 * the two name fields, so this is a courtesy to the reader rather than the
 * actual guard.
 */
export default function IdentitySection({ buyer }: { buyer: Buyer }) {
    const { t } = useTranslation();
    const [editing, setEditing] = useState(false);

    // `?? ''`: a buyer from the fast-signup flow has never had a name at all
    // (RegistrationCodeService::complete leaves both columns null), and an
    // <input> given `null` as its value logs a React warning about switching
    // between controlled and uncontrolled — the empty string is what an
    // unfilled text field actually is.
    const form = useForm({
        first_name: buyer.first_name ?? '',
        last_name: buyer.last_name ?? '',
    });

    // Re-synced from the current prop on every open, not just at mount — a
    // save closes the form rather than unmounting it, so a stale `form.data`
    // from before that save would otherwise show up the next time it opens.
    const startEditing = () => {
        form.setData({
            first_name: buyer.first_name ?? '',
            last_name: buyer.last_name ?? '',
        });
        form.clearErrors();
        setEditing(true);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.patch(route('buyer.profile.update'), {
            preserveScroll: true,
            onSuccess: () => {
                setEditing(false);
                toast.success(t('buyer.account.saved'));
            },
        });
    };

    return (
        <Section
            title={t('buyer.account.section_identity')}
            description={t('buyer.account.section_identity_desc')}
            action={
                !editing && (
                    <EditButton
                        label={t('common.edit')}
                        onClick={startEditing}
                    />
                )
            }
        >
            <div className="space-y-4">
                {editing ? (
                    <form onSubmit={submit} className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                label={t('buyer.account.field_first_name')}
                                htmlFor="first_name"
                                error={form.errors.first_name}
                            >
                                <input
                                    id="first_name"
                                    autoFocus
                                    autoComplete="given-name"
                                    value={form.data.first_name}
                                    onChange={(e) =>
                                        form.setData(
                                            'first_name',
                                            e.target.value,
                                        )
                                    }
                                    className={INPUT}
                                />
                            </Field>

                            <Field
                                label={t('buyer.account.field_last_name')}
                                htmlFor="last_name"
                                error={form.errors.last_name}
                            >
                                <input
                                    id="last_name"
                                    autoComplete="family-name"
                                    value={form.data.last_name}
                                    onChange={(e) =>
                                        form.setData(
                                            'last_name',
                                            e.target.value,
                                        )
                                    }
                                    className={INPUT}
                                />
                            </Field>
                        </div>

                        <FormActions
                            submitLabel={t('common.save')}
                            cancelLabel={t('common.cancel')}
                            processing={form.processing}
                            onCancel={() => setEditing(false)}
                        />
                    </form>
                ) : (
                    <ViewRow
                        label={t('buyer.account.field_full_name')}
                        value={
                            // Neither field is set for a fast-signup buyer who
                            // hasn't opened this section yet — say so, rather
                            // than rendering the literal string "null null".
                            buyer.first_name || buyer.last_name
                                ? `${buyer.first_name ?? ''} ${buyer.last_name ?? ''}`.trim()
                                : t('buyer.account.field_full_name_empty')
                        }
                    />
                )}

                <div className="grid gap-4 sm:grid-cols-2">
                    <LockedField
                        label={t('buyer.account.field_email')}
                        value={buyer.email}
                    />
                    <LockedField
                        label={t('buyer.account.field_phone')}
                        value={buyer.phone_number}
                    />
                </div>

                <p className="text-xs leading-relaxed text-souk-brown-deep/45">
                    {t('buyer.account.locked_note')}
                </p>
            </div>
        </Section>
    );
}
