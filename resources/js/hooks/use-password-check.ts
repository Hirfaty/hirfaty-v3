import type { RequestPayload } from '@inertiajs/core';
import { useState } from 'react';
import { useTranslation } from '@/hooks/use-translation';
import { passwordErrors } from '@/lib/password-rules';

type PasswordField = 'password' | 'password_confirmation';

function read(data: RequestPayload, field: PasswordField): string {
    const value = data instanceof FormData ? data.get(field) : data[field];

    return typeof value === 'string' ? value : '';
}

/**
 * Checks `password` / `password_confirmation` in the browser before an Inertia
 * `<Form>` posts. Pass `onBefore` to the `<Form>` — returning `false` from it
 * cancels the visit — and render `errors.<field>` ahead of the server's own
 * message for that field.
 *
 * The backend keeps validating regardless (PasswordValidationRules): this only
 * spares a round trip for a password it would reject, and a disabled script or
 * a hand-rolled request skips it entirely.
 */
export function usePasswordCheck() {
    const { t } = useTranslation();
    const [errors, setErrors] = useState<
        Partial<Record<PasswordField, string>>
    >({});

    const onBefore = ({ data }: { data: RequestPayload }) => {
        const failed = passwordErrors(
            read(data, 'password'),
            read(data, 'password_confirmation'),
        );
        const fields = Object.keys(failed) as PasswordField[];

        setErrors(
            Object.fromEntries(
                fields.map((field) => [
                    field,
                    t(`common.auth.login.${failed[field]}`),
                ]),
            ),
        );

        if (fields.length === 0) {
            return;
        }

        document.getElementById(fields[0])?.focus();

        return false;
    };

    // Typing into a field retires its stale client-side message.
    const clearError = (field: PasswordField) =>
        setErrors((current) => {
            if (!current[field]) {
                return current;
            }

            const next = { ...current };
            delete next[field];

            return next;
        });

    return { errors, onBefore, clearError };
}
