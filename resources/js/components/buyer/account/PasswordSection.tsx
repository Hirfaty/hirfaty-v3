import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import { useTranslation } from '@/hooks/use-translation';
import {
    EditButton,
    Field,
    FormActions,
    INPUT,
    Section,
    ViewRow,
} from './form';

/**
 * Masked at rest; the pencil opens the current/new/confirm form.
 *
 * Posts to the shared `user-password.update` (Settings\SecurityController),
 * which already validates the current password and returns back() — so the
 * account area needs no endpoint of its own for this.
 */
export default function PasswordSection() {
    const { t } = useTranslation();
    const [editing, setEditing] = useState(false);

    const form = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const startEditing = () => {
        form.clearErrors();
        setEditing(true);
    };

    const cancel = () => {
        form.reset();
        form.clearErrors();
        setEditing(false);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.put(route('user-password.update'), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setEditing(false);
                toast.success(t('buyer.account.password_saved'));
            },
            /*
             * The new pair has to be retyped after any rejection, so clear it
             * rather than leave it in component state. `current_password` is
             * kept on purpose: the failure is often a weak *new* password, and
             * making them retype a correct current one to fix that is a
             * pointless punishment.
             */
            onError: () => form.reset('password', 'password_confirmation'),
        });
    };

    return (
        <Section
            title={t('buyer.account.section_password')}
            description={t('buyer.account.section_password_desc')}
            action={
                !editing && (
                    <EditButton
                        label={t('buyer.account.change_password')}
                        onClick={startEditing}
                    />
                )
            }
        >
            {editing ? (
                <form onSubmit={submit} className="space-y-4">
                    <Field
                        label={t('buyer.account.field_current_password')}
                        htmlFor="current_password"
                        error={form.errors.current_password}
                    >
                        <input
                            id="current_password"
                            type="password"
                            autoFocus
                            autoComplete="current-password"
                            value={form.data.current_password}
                            onChange={(e) =>
                                form.setData('current_password', e.target.value)
                            }
                            className={INPUT}
                        />
                    </Field>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field
                            label={t('buyer.account.field_new_password')}
                            htmlFor="password"
                            error={form.errors.password}
                        >
                            <input
                                id="password"
                                type="password"
                                autoComplete="new-password"
                                value={form.data.password}
                                onChange={(e) =>
                                    form.setData('password', e.target.value)
                                }
                                className={INPUT}
                            />
                        </Field>

                        <Field
                            label={t('buyer.account.field_confirm_password')}
                            htmlFor="password_confirmation"
                            error={form.errors.password_confirmation}
                        >
                            <input
                                id="password_confirmation"
                                type="password"
                                autoComplete="new-password"
                                value={form.data.password_confirmation}
                                onChange={(e) =>
                                    form.setData(
                                        'password_confirmation',
                                        e.target.value,
                                    )
                                }
                                className={INPUT}
                            />
                        </Field>
                    </div>

                    <FormActions
                        submitLabel={t('buyer.account.update_password')}
                        cancelLabel={t('common.cancel')}
                        processing={form.processing}
                        onCancel={cancel}
                    />
                </form>
            ) : (
                <ViewRow
                    label={t('buyer.account.field_password')}
                    value="••••••••••"
                />
            )}
        </Section>
    );
}
