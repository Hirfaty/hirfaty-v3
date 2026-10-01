/**
 * One check per rule in Password::defaults() (App\Providers\AppServiceProvider)
 * — min 12 chars, both cases, a number, a symbol. Shared by the strength meter
 * and the pre-submit check on the signup forms, so neither can drift from the
 * other; the server still enforces the real rule (PasswordValidationRules), this
 * only spares the round trip for a password it would reject anyway.
 *
 * Deliberately no `uncompromised()` equivalent: that one is production-only
 * and needs the HIBP lookup, so it stays a server-side error.
 */
export const PASSWORD_CHECKS: Array<(password: string) => boolean> = [
    (password) => password.length >= 12,
    (password) => /[a-z]/.test(password) && /[A-Z]/.test(password),
    (password) => /[0-9]/.test(password),
    (password) => /[^a-zA-Z0-9]/.test(password),
];

export type PasswordErrorKey =
    | 'password_error_rules'
    | 'password_error_mismatch';

/**
 * What the server would say about this pair, as `common.auth.login.*`
 * translation keys per field. Empty object means it would pass.
 */
export function passwordErrors(
    password: string,
    confirmation: string,
): Partial<Record<'password' | 'password_confirmation', PasswordErrorKey>> {
    const errors: Partial<
        Record<'password' | 'password_confirmation', PasswordErrorKey>
    > = {};

    if (!PASSWORD_CHECKS.every((check) => check(password))) {
        errors.password = 'password_error_rules';
    }

    if (password !== confirmation) {
        errors.password_confirmation = 'password_error_mismatch';
    }

    return errors;
}
