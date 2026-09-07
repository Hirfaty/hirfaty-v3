import { Lock, Pencil } from 'lucide-react';
import type { ReactNode } from 'react';

/*
 * The account area's form vocabulary.
 *
 * Nothing is boxed — sections are separated by a hairline and whitespace, in
 * keeping with the rail beside them. Inputs carry the only borders on the
 * page, so the eye reads them as the things you can actually type into.
 *
 * Every editable section is view-first: a value or a placeholder sits there
 * until the pencil is clicked, and only then does it become a form. Nothing
 * looks editable by default, which is the point.
 */

export const INPUT =
    'w-full rounded-lg border border-souk-brown/25 bg-white px-3.5 py-2.5 text-sm text-souk-title transition-colors placeholder:text-souk-brown-deep/30 focus:border-souk-brown focus:ring-2 focus:ring-souk-brown/10 focus:outline-none';

export const INPUT_LOCKED =
    'w-full cursor-not-allowed rounded-lg border border-souk-brown/12 bg-souk-cream/30 px-3.5 py-2.5 text-sm text-souk-brown-deep/60';

export const LABEL =
    'mb-1.5 block text-[11px] font-medium tracking-[0.08em] text-souk-brown-deep/55 uppercase';

export const BUTTON =
    'inline-flex items-center gap-2 rounded-lg bg-souk-brown px-5 py-2.5 text-sm text-souk-paper transition-colors hover:bg-souk-brown-deep disabled:cursor-not-allowed disabled:opacity-40';

/** One block of the page: a heading, a blurb, and the fields under it. */
export function Section({
    title,
    description,
    action,
    children,
}: {
    title: string;
    description?: string;
    /** The edit-pencil, or whatever else belongs beside the heading. */
    action?: ReactNode;
    children: ReactNode;
}) {
    return (
        <section className="border-t border-souk-brown/12 pt-10 first:border-0 first:pt-0">
            <div className="flex items-start justify-between gap-4">
                <div>
                    <h2 className="font-display text-xl leading-tight text-souk-title">
                        {title}
                    </h2>
                    {description && (
                        <p className="mt-1.5 max-w-lg text-sm leading-relaxed text-souk-brown-deep/60">
                            {description}
                        </p>
                    )}
                </div>
                {action}
            </div>
            <div className="mt-6 max-w-xl">{children}</div>
        </section>
    );
}

/** Opens a section's edit form. Hidden once that form is open. */
export function EditButton({
    label,
    onClick,
}: {
    label: string;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className="inline-flex shrink-0 items-center gap-1.5 py-1 text-xs text-souk-brown-deep/60 transition-colors hover:text-souk-brown"
        >
            <Pencil className="h-3.5 w-3.5" strokeWidth={1.8} />
            {label}
        </button>
    );
}

/** A value shown at rest, before the pencil turns it into a field. */
export function ViewRow({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div>
            <p className={LABEL}>{label}</p>
            <p className="text-sm text-souk-title">{value}</p>
        </div>
    );
}

/** Save + cancel, the pair every inline edit form ends on. */
export function FormActions({
    submitLabel,
    cancelLabel,
    processing,
    onCancel,
}: {
    submitLabel: string;
    cancelLabel: string;
    processing: boolean;
    onCancel: () => void;
}) {
    return (
        <div className="flex items-center gap-2 pt-1">
            <button type="submit" disabled={processing} className={BUTTON}>
                {submitLabel}
            </button>
            <button
                type="button"
                onClick={onCancel}
                className="px-3 py-2.5 text-sm text-souk-brown-deep/55 transition-colors hover:text-souk-brown"
            >
                {cancelLabel}
            </button>
        </div>
    );
}

export function Field({
    label,
    htmlFor,
    error,
    hint,
    children,
}: {
    label: string;
    htmlFor?: string;
    error?: string;
    hint?: string;
    children: ReactNode;
}) {
    return (
        <div>
            <label htmlFor={htmlFor} className={LABEL}>
                {label}
            </label>
            {children}
            {error ? (
                <p className="mt-1.5 text-xs text-terracotta">{error}</p>
            ) : (
                hint && (
                    <p className="mt-1.5 text-[11px] text-souk-brown-deep/45">
                        {hint}
                    </p>
                )
            )}
        </div>
    );
}

/**
 * A value the buyer can see but not change.
 *
 * Rendered as a real disabled input rather than plain text so it keeps its
 * place in the grid beside the editable fields, and reads as "this is a field,
 * it is just closed" — the padlock says why.
 */
export function LockedField({
    label,
    value,
}: {
    label: string;
    value: string | null;
}) {
    return (
        <div>
            <span className={LABEL}>{label}</span>
            <div className="relative">
                <input
                    readOnly
                    disabled
                    value={value ?? '—'}
                    className={`${INPUT_LOCKED} pe-9`}
                />
                <Lock
                    aria-hidden="true"
                    className="pointer-events-none absolute end-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-souk-brown-deep/35"
                    strokeWidth={1.8}
                />
            </div>
        </div>
    );
}
