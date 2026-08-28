import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowRight, ImageUp, Package, Send, X } from 'lucide-react';
import type { ReactNode } from 'react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';
import { RichEditor } from '@/components/rich-editor';
import AdminLayout from '@/layouts/admin/layout';
import type { Category, PageProps } from '@/types';

interface Props extends PageProps {
    categories: Category[];
    store?: {
        id: number;
        store_name: string;
        user?: {
            id: number;
            first_name: string;
            last_name: string;
        };
    };
}

const MAX_IMAGE_SIZE = 5 * 1024 * 1024; // 5MB
const MAX_VIDEO_SIZE = 50 * 1024 * 1024; // 50MB

const inputClass =
    'w-full rounded-lg border border-ink/15 bg-paper px-3.5 py-2.5 text-sm text-ink transition-colors placeholder:text-ink-soft/50 focus:border-terracotta focus:ring-2 focus:ring-terracotta/15 focus:outline-none';

/** Label + optional required marker + helper text + error, as in the reference layout. */
function Field({
    label,
    required,
    hint,
    error,
    children,
}: {
    label: string;
    required?: boolean;
    hint?: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div>
            <label className="mb-1.5 block text-sm font-medium tracking-normal text-ink normal-case">
                {label}
                {required && <span className="ms-0.5 text-destructive">*</span>}
            </label>
            {children}
            {error ? (
                <p className="mt-1.5 text-xs text-destructive">{error}</p>
            ) : hint ? (
                <p className="mt-1.5 text-xs text-ink-soft">{hint}</p>
            ) : null}
        </div>
    );
}

const AdminProductCreate = ({ categories, store }: Props) => {
    const { data, setData, post, transform, processing, errors } = useForm({
        store_id: store?.id ?? '',
        name: '',
        description: '',
        category_id: categories[0]?.id ?? '',
        base_price: '',
        sale_price: '',
        stock: 1,
        media: [] as File[],
        status: 'published',
    });

    const [dragging, setDragging] = useState(false);
    const fileInput = useRef<HTMLInputElement>(null);

    const addFiles = (files: FileList | null) => {
        if (!files || files.length === 0) {
            return;
        }

        const valid: File[] = [];
        const tooLarge: string[] = [];

        Array.from(files).forEach((f) => {
            const isVideo = f.type.startsWith('video/');
            const limit = isVideo ? MAX_VIDEO_SIZE : MAX_IMAGE_SIZE;

            if (f.size > limit) {
                tooLarge.push(
                    `${f.name} (${isVideo ? 'Vidéo > 50Mo' : 'Image > 5Mo'})`,
                );
            } else {
                valid.push(f);
            }
        });

        if (tooLarge.length > 0) {
            toast.error('Fichiers trop volumineux', {
                description: tooLarge.join(', '),
            });
        }

        if (valid.length === 0) {
            return;
        }

        setData('media', [...data.media, ...valid]);
    };

    const removeMediaFile = (index: number) => {
        setData(
            'media',
            data.media.filter((_, i) => i !== index),
        );
    };

    // Two submit buttons, one form: the button picks the status it saves with.
    const submitAs = (status: 'draft' | 'published') => {
        transform((current) => ({ ...current, status }));
        post(route('admin.products.store'), { forceFormData: true });
    };

    const backHref = store?.user
        ? route('admin.users.show', store.user.id)
        : '/admin/products';

    return (
        <div className="min-h-full p-6 md:p-10">
            <Head title="Créer un produit admin" />

            <div className="mx-auto max-w-2xl">
                <div className="rounded-2xl border border-ink/10 bg-paper p-5 shadow-[0_2px_12px_rgba(28,33,40,0.06)] md:p-6">
                    {/* Header */}
                    <div className="mb-5 flex items-start justify-between gap-4">
                        <div className="flex items-start gap-3">
                            <div className="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-terracotta/10">
                                <Package className="h-4 w-4 text-terracotta" />
                            </div>
                            <div>
                                <h1 className="font-display text-xl leading-tight text-ink">
                                    {store
                                        ? `Create product for ${store.store_name}`
                                        : 'Create a new product'}
                                </h1>
                                <p className="mt-1 text-sm text-ink-soft">
                                    {store?.user
                                        ? `Seller: ${store.user.first_name} ${store.user.last_name}`
                                        : 'Add a new product to a seller store.'}
                                </p>
                            </div>
                        </div>
                        <Link
                            href={backHref}
                            aria-label="Fermer"
                            className="rounded-md p-1.5 text-ink-soft transition-colors hover:bg-cream-deep/50 hover:text-ink"
                        >
                            <X className="h-4 w-4" />
                        </Link>
                    </div>

                    {!store ? (
                        <div className="rounded-xl border border-amber-200 bg-amber-50 p-6 text-sm text-amber-900">
                            <p className="font-semibold">
                                Aucun store sélectionné.
                            </p>
                            <p className="mt-2">
                                Sélectionnez d'abord un vendeur depuis la fiche
                                utilisateur, puis relancez cette page avec{' '}
                                <code>?store_id=...</code>.
                            </p>
                        </div>
                    ) : (
                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                submitAs('published');
                            }}
                        >
                            {/* Inner panel holding the fields */}
                            <div className="space-y-5 rounded-xl border border-ink/10 bg-cream/40 p-5">
                                <Field
                                    label="Product Name"
                                    required
                                    hint="Give your product a short and clear name."
                                    error={errors.name}
                                >
                                    <input
                                        value={data.name}
                                        onChange={(e) =>
                                            setData('name', e.target.value)
                                        }
                                        placeholder="Tajine émaillé de Fès"
                                        className={inputClass}
                                    />
                                </Field>

                                <Field
                                    label="Category"
                                    required
                                    hint="Pick the trade this piece belongs to."
                                    error={errors.category_id}
                                >
                                    <select
                                        value={data.category_id}
                                        onChange={(e) =>
                                            setData(
                                                'category_id',
                                                Number(e.target.value),
                                            )
                                        }
                                        className={inputClass}
                                    >
                                        {categories.map((category) => (
                                            <option
                                                key={category.id}
                                                value={category.id}
                                            >
                                                {category.name}
                                            </option>
                                        ))}
                                    </select>
                                </Field>

                                <Field
                                    label="Description"
                                    hint="Tell the story behind the piece — materials, technique, origin."
                                    error={errors.description}
                                >
                                    <RichEditor
                                        value={data.description}
                                        onChange={(html) =>
                                            setData('description', html)
                                        }
                                    />
                                </Field>

                                <div className="grid gap-5 sm:grid-cols-3">
                                    <Field
                                        label="Base Price"
                                        required
                                        hint="In MAD."
                                        error={errors.base_price}
                                    >
                                        <input
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            value={data.base_price}
                                            onChange={(e) =>
                                                setData(
                                                    'base_price',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="0.00"
                                            className={inputClass}
                                        />
                                    </Field>

                                    <Field
                                        label="Sale Price"
                                        hint="Optional, below base."
                                        error={errors.sale_price}
                                    >
                                        <input
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            value={data.sale_price}
                                            onChange={(e) =>
                                                setData(
                                                    'sale_price',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="0.00"
                                            className={inputClass}
                                        />
                                    </Field>

                                    <Field
                                        label="Stock"
                                        required
                                        hint="Units available."
                                        error={errors.stock}
                                    >
                                        <input
                                            type="number"
                                            min="0"
                                            value={data.stock}
                                            onChange={(e) =>
                                                setData(
                                                    'stock',
                                                    Number(e.target.value),
                                                )
                                            }
                                            className={inputClass}
                                        />
                                    </Field>
                                </div>

                                {/* Media dropzone */}
                                <Field
                                    label="Media"
                                    hint="Add up to 5 images or videos. Used to represent the product across the marketplace. Images up to 5 Mo, videos up to 50 Mo."
                                    error={errors.media ?? errors['media.0']}
                                >
                                    <div
                                        onDragOver={(e) => {
                                            e.preventDefault();
                                            setDragging(true);
                                        }}
                                        onDragLeave={() => setDragging(false)}
                                        onDrop={(e) => {
                                            e.preventDefault();
                                            setDragging(false);
                                            addFiles(e.dataTransfer.files);
                                        }}
                                        className={`flex flex-col items-center justify-center rounded-xl border border-dashed px-6 py-10 text-center transition-colors ${
                                            dragging
                                                ? 'border-terracotta bg-terracotta/5'
                                                : 'border-ink/20 bg-paper'
                                        }`}
                                    >
                                        <ImageUp
                                            className="h-6 w-6 text-ink-soft"
                                            strokeWidth={1.5}
                                        />
                                        <p className="mt-3 text-sm text-ink-soft">
                                            Drop your images here, or
                                        </p>
                                        <button
                                            type="button"
                                            onClick={() =>
                                                fileInput.current?.click()
                                            }
                                            className="mt-2 rounded-lg border border-ink/15 bg-paper px-3.5 py-1.5 text-sm font-medium text-ink shadow-[0_1px_2px_rgba(28,33,40,0.05)] transition-colors hover:bg-cream-deep/40"
                                        >
                                            Click to browse
                                        </button>
                                        <input
                                            ref={fileInput}
                                            type="file"
                                            multiple
                                            accept="image/*,video/*"
                                            onChange={(e) => {
                                                addFiles(e.target.files);
                                                e.target.value = '';
                                            }}
                                            className="hidden"
                                        />
                                    </div>
                                </Field>

                                {data.media.length > 0 && (
                                    <ul className="space-y-2">
                                        {data.media.map((file, index) => (
                                            <li
                                                key={`${file.name}-${index}`}
                                                className="flex items-center justify-between gap-3 rounded-lg border border-ink/10 bg-paper px-3 py-2.5"
                                            >
                                                <div className="min-w-0">
                                                    <p className="truncate text-sm font-medium text-ink">
                                                        {file.name}
                                                    </p>
                                                    <p className="text-xs text-ink-soft">
                                                        {file.type ||
                                                            'unknown type'}{' '}
                                                        ·{' '}
                                                        {Math.round(
                                                            file.size / 1024,
                                                        )}{' '}
                                                        KB
                                                    </p>
                                                </div>
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        removeMediaFile(index)
                                                    }
                                                    aria-label={`Retirer ${file.name}`}
                                                    className="shrink-0 rounded-md p-1.5 text-ink-soft transition-colors hover:bg-cream-deep/50 hover:text-destructive"
                                                >
                                                    <X className="h-4 w-4" />
                                                </button>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </div>

                            {/* Actions sit outside the panel, as in the reference */}
                            <div className="mt-5 flex flex-col-reverse gap-3 sm:flex-row sm:items-center">
                                <button
                                    type="button"
                                    disabled={processing}
                                    onClick={() => submitAs('draft')}
                                    className="inline-flex items-center justify-center gap-2 rounded-lg bg-cream-deep/60 px-5 py-2.5 text-sm font-medium text-ink transition-colors hover:bg-cream-deep disabled:opacity-50"
                                >
                                    Save as draft
                                </button>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="btn-swipe inline-flex flex-1 items-center justify-center gap-2 rounded-lg bg-terracotta px-5 py-2.5 text-sm font-medium text-cream transition-colors duration-300 disabled:opacity-50 [--swipe-bg:var(--ink)]"
                                >
                                    {processing ? (
                                        'Saving...'
                                    ) : (
                                        <>
                                            Publish product
                                            <ArrowRight className="h-4 w-4" />
                                        </>
                                    )}
                                </button>
                            </div>

                            <p className="mt-3 flex items-center justify-center gap-1.5 text-xs text-ink-soft">
                                <Send className="h-3 w-3" />
                                A draft stays hidden until it is published.
                            </p>
                        </form>
                    )}
                </div>
            </div>
        </div>
    );
};

AdminProductCreate.layout = (page: ReactNode) => (
    <AdminLayout children={page} />
);
export default AdminProductCreate;
