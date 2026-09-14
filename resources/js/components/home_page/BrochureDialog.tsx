import { useForm, usePage } from '@inertiajs/react';
import { Download } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';
import { toast } from 'sonner';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { useTranslation } from '@/hooks/use-translation';
import brochure from '@/routes/brochure';

/** Mirrors `BrochureLead::TYPES`. */
const TYPES = ['buyer', 'seller'] as const;

/** Mirrors `BrochureLead::SELLER_TYPES`, which mirrors the `stores.type` enum. */
const SELLER_TYPES = ['artist', 'company', 'cooperative'] as const;

const fieldClass =
    'w-full rounded-lg border border-souk-brown/15 bg-white px-3.5 py-2.5 text-sm text-souk-title transition-colors placeholder:text-souk-title/35 focus:border-souk-brown focus:outline-none';

const labelClass =
    'mb-1.5 block text-[11px] font-semibold tracking-[0.12em] text-souk-title/50 uppercase';

function FieldError({ children }: { children?: ReactNode }) {
    if (!children) {
        return null;
    }

    return <p className="mt-1.5 text-xs text-terracotta">{children}</p>;
}

/**
 * The form that stands between a visitor and the brochure PDF.
 *
 * The download is deliberately not a link. The server records the lead, marks
 * the session, and only then will `brochure.download` answer — so the file has
 * to be fetched in a second step, once this form has come back successful. That
 * is what the `window.location` assignment in `onSuccess` is for: a plain
 * navigation, which the browser turns into a save rather than a page change
 * because the response carries `Content-Disposition: attachment`.
 *
 * `errorBag` matters for the same reason it does in the footer's newsletter
 * form: Inertia hands a page one flat `errors` object, and both forms live on
 * the homepage with a field called `email`. The server puts them in the same
 * bag; see `BrochureLeadRequest`.
 */
export default function BrochureDialog({ children }: { children: ReactNode }) {
    const { t } = useTranslation();
    const page = usePage<{
        flash?: { success?: string };
        errors?: { brochure?: Record<string, string> };
    }>();
    const [open, setOpen] = useState(false);

    const form = useForm({
        first_name: '',
        last_name: '',
        email: '',
        type: 'buyer' as (typeof TYPES)[number],
        seller_type: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        form.post(brochure.request.url(), {
            errorBag: 'brochure',
            // The section sits well down the homepage; without this, a
            // successful submit throws the reader back to the top of it.
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                form.reset();
                // Falls back to the local translation so the confirmation
                // survives the flash going missing for any reason.
                toast.success(
                    page.props.flash?.success ??
                        t('welcome.brochure.dialog.success'),
                );
                window.location.href = brochure.download.url();
            },
        });
    };

    const isSeller = form.data.type === 'seller';

    /*
     * Raised by the controller when the PDF is missing from the disk. Read off
     * the page rather than `form.errors`, which is typed to the form's own
     * fields — this one belongs to no field, which is the point of it: it says
     * the brochure itself is unavailable, not that anything was filled in
     * wrongly. Still a validation error rather than a flashed message so that
     * `onSuccess` does not fire and send the browser after a file that is not
     * there.
     */
    const unavailable = page.props.errors?.brochure?.brochure;

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{children}</DialogTrigger>

            <DialogContent className="max-w-md bg-souk-paper sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle className="font-display text-xl text-souk-title">
                        {t('welcome.brochure.dialog.title')}
                    </DialogTitle>
                    <DialogDescription className="text-sm text-souk-title/60">
                        {t('welcome.brochure.dialog.lead')}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="mt-2 space-y-4">
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label
                                htmlFor="brochure-first-name"
                                className={labelClass}
                            >
                                {t('welcome.brochure.dialog.first_name')}
                            </label>
                            <input
                                id="brochure-first-name"
                                name="given-name"
                                autoComplete="given-name"
                                value={form.data.first_name}
                                onChange={(e) =>
                                    form.setData('first_name', e.target.value)
                                }
                                aria-invalid={
                                    form.errors.first_name ? true : undefined
                                }
                                className={fieldClass}
                            />
                            <FieldError>{form.errors.first_name}</FieldError>
                        </div>

                        <div>
                            <label
                                htmlFor="brochure-last-name"
                                className={labelClass}
                            >
                                {t('welcome.brochure.dialog.last_name')}
                            </label>
                            <input
                                id="brochure-last-name"
                                name="family-name"
                                autoComplete="family-name"
                                value={form.data.last_name}
                                onChange={(e) =>
                                    form.setData('last_name', e.target.value)
                                }
                                aria-invalid={
                                    form.errors.last_name ? true : undefined
                                }
                                className={fieldClass}
                            />
                            <FieldError>{form.errors.last_name}</FieldError>
                        </div>
                    </div>

                    <div>
                        <label htmlFor="brochure-email" className={labelClass}>
                            {t('welcome.brochure.dialog.email')}
                        </label>
                        <input
                            id="brochure-email"
                            type="email"
                            autoComplete="email"
                            // Latin-script even in Arabic: an address is never
                            // written right-to-left, and letting the paragraph
                            // direction take over reorders what is typed.
                            dir="ltr"
                            placeholder="vous@exemple.com"
                            value={form.data.email}
                            onChange={(e) =>
                                form.setData('email', e.target.value)
                            }
                            aria-invalid={form.errors.email ? true : undefined}
                            className={`${fieldClass} rtl:text-end`}
                        />
                        <FieldError>{form.errors.email}</FieldError>
                    </div>

                    <fieldset>
                        <legend className={labelClass}>
                            {t('welcome.brochure.dialog.type')}
                        </legend>
                        <div className="grid grid-cols-2 gap-3">
                            {TYPES.map((type) => (
                                <label
                                    key={type}
                                    className={`cursor-pointer rounded-lg border px-3.5 py-2.5 text-center text-sm transition-colors ${
                                        form.data.type === type
                                            ? 'border-souk-brown bg-souk-brown text-white'
                                            : 'border-souk-brown/15 bg-white text-souk-title hover:border-souk-brown/40'
                                    }`}
                                >
                                    <input
                                        type="radio"
                                        name="type"
                                        value={type}
                                        checked={form.data.type === type}
                                        onChange={() =>
                                            form.setData('type', type)
                                        }
                                        className="sr-only"
                                    />
                                    {t(`welcome.brochure.dialog.type.${type}`)}
                                </label>
                            ))}
                        </div>
                        <FieldError>{form.errors.type}</FieldError>
                    </fieldset>

                    {/* Asked only of sellers — see `BrochureLeadRequest`, where
                        it is `required_if:type,seller`, and the service, which
                        drops whatever is left here if they switch back. */}
                    {isSeller && (
                        <div>
                            <label
                                htmlFor="brochure-seller-type"
                                className={labelClass}
                            >
                                {t('welcome.brochure.dialog.seller_type')}
                            </label>
                            <select
                                id="brochure-seller-type"
                                value={form.data.seller_type}
                                onChange={(e) =>
                                    form.setData('seller_type', e.target.value)
                                }
                                aria-invalid={
                                    form.errors.seller_type ? true : undefined
                                }
                                className={fieldClass}
                            >
                                <option value="">
                                    {t(
                                        'welcome.brochure.dialog.seller_type.placeholder',
                                    )}
                                </option>
                                {SELLER_TYPES.map((sellerType) => (
                                    <option key={sellerType} value={sellerType}>
                                        {t(
                                            `welcome.brochure.dialog.seller_type.${sellerType}`,
                                        )}
                                    </option>
                                ))}
                            </select>
                            <FieldError>{form.errors.seller_type}</FieldError>
                        </div>
                    )}

                    <FieldError>{unavailable}</FieldError>

                    <button
                        type="submit"
                        disabled={form.processing}
                        className="mt-2 inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl bg-souk-brown px-6 py-3 text-sm font-semibold tracking-[0.04em] text-white uppercase transition-colors hover:bg-souk-brown-deep disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <Download
                            className="h-4 w-4 shrink-0"
                            strokeWidth={2}
                        />
                        {form.processing
                            ? t('welcome.brochure.dialog.submitting')
                            : t('welcome.brochure.dialog.submit')}
                    </button>
                </form>
            </DialogContent>
        </Dialog>
    );
}
