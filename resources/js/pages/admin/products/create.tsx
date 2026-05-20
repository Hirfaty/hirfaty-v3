import AdminLayout from '@/layouts/admin/layout';
import type { Category, PageProps } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, ArrowRight } from 'lucide-react';
import type { ReactNode } from 'react';

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

const AdminProductCreate = ({ categories, store }: Props) => {
    const { data, setData, post, processing, errors } = useForm({
        store_id: store?.id ?? '',
        name: '',
        description: '',
        category_id: categories[0]?.id ?? '',
        base_price: '',
        sale_price: '',
        stock: 1,
        media: [] as File[],
    });

    const handleMedia = (event: React.ChangeEvent<HTMLInputElement>) => {
        const files = event.target.files;
        if (!files) return;

        const newFiles = Array.from(files);
        setData('media', [...data.media, ...newFiles]);
        event.target.value = '';
    };

    const removeMediaFile = (index: number) => {
        setData(
            'media',
            data.media.filter((_, i) => i !== index),
        );
    };

    return (
        <div className="mx-auto max-w-5xl p-6 md:p-8">
            <Head title="Créer un produit admin" />

            <Link
                href={
                    store?.user
                        ? route('admin.users.show', store.user.id)
                        : '/admin/products'
                }
                className="mb-8 inline-flex items-center gap-2 text-sm text-ink-soft transition-colors hover:text-ink"
            >
                <ArrowLeft className="h-4 w-4" /> Retour
            </Link>

            <div className="rounded-3xl border border-ink/10 bg-paper p-8 shadow-sm">
                <div className="mb-8">
                    <p className="text-xs font-medium tracking-[0.2em] text-ink-soft uppercase">
                        Admin product flow
                    </p>
                    <h1 className="mt-4 text-4xl font-semibold text-ink">
                        {store
                            ? `Create product for ${store.store_name}`
                            : 'Create a new product'}
                    </h1>
                    {store?.user && (
                        <p className="mt-2 text-sm text-ink-soft">
                            Seller: {store.user.first_name}{' '}
                            {store.user.last_name}
                        </p>
                    )}
                </div>

                {!store ? (
                    <div className="rounded-2xl border border-amber-200 bg-amber-50 p-8 text-sm text-amber-900">
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
                            post(route('admin.products.store'), {
                                forceFormData: true,
                            });
                        }}
                        className="space-y-8"
                    >
                        <input type="hidden" value={data.store_id} />

                        <div className="grid gap-6 md:grid-cols-2">
                            <div>
                                <label className="mb-2 block text-[10px] font-bold tracking-widest text-ink/40 uppercase">
                                    Product Name
                                </label>
                                <input
                                    value={data.name}
                                    onChange={(event) =>
                                        setData('name', event.target.value)
                                    }
                                    className="w-full rounded-2xl border border-ink/10 bg-white px-4 py-3 text-sm focus:border-terracotta focus:outline-none"
                                />
                                {errors.name && (
                                    <p className="mt-2 text-[10px] text-rose-600">
                                        {errors.name}
                                    </p>
                                )}
                            </div>

                            <div>
                                <label className="mb-2 block text-[10px] font-bold tracking-widest text-ink/40 uppercase">
                                    Category
                                </label>
                                <select
                                    value={data.category_id}
                                    onChange={(event) =>
                                        setData(
                                            'category_id',
                                            event.target.value,
                                        )
                                    }
                                    className="w-full rounded-2xl border border-ink/10 bg-white px-4 py-3 text-sm focus:border-terracotta focus:outline-none"
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
                                {errors.category_id && (
                                    <p className="mt-2 text-[10px] text-rose-600">
                                        {errors.category_id}
                                    </p>
                                )}
                            </div>
                        </div>

                        <div>
                            <label className="mb-2 block text-[10px] font-bold tracking-widest text-ink/40 uppercase">
                                Description
                            </label>
                            <textarea
                                rows={5}
                                value={data.description}
                                onChange={(event) =>
                                    setData('description', event.target.value)
                                }
                                className="w-full rounded-2xl border border-ink/10 bg-white px-4 py-3 text-sm focus:border-terracotta focus:outline-none"
                            />
                            {errors.description && (
                                <p className="mt-2 text-[10px] text-rose-600">
                                    {errors.description}
                                </p>
                            )}
                        </div>

                        <div className="grid gap-6 md:grid-cols-3">
                            <div>
                                <label className="mb-2 block text-[10px] font-bold tracking-widest text-ink/40 uppercase">
                                    Base Price
                                </label>
                                <input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={data.base_price}
                                    onChange={(event) =>
                                        setData(
                                            'base_price',
                                            event.target.value,
                                        )
                                    }
                                    className="w-full rounded-2xl border border-ink/10 bg-white px-4 py-3 text-sm focus:border-terracotta focus:outline-none"
                                />
                                {errors.base_price && (
                                    <p className="mt-2 text-[10px] text-rose-600">
                                        {errors.base_price}
                                    </p>
                                )}
                            </div>

                            <div>
                                <label className="mb-2 block text-[10px] font-bold tracking-widest text-ink/40 uppercase">
                                    Sale Price
                                </label>
                                <input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={data.sale_price}
                                    onChange={(event) =>
                                        setData(
                                            'sale_price',
                                            event.target.value,
                                        )
                                    }
                                    className="w-full rounded-2xl border border-ink/10 bg-white px-4 py-3 text-sm focus:border-terracotta focus:outline-none"
                                />
                                {errors.sale_price && (
                                    <p className="mt-2 text-[10px] text-rose-600">
                                        {errors.sale_price}
                                    </p>
                                )}
                            </div>

                            <div>
                                <label className="mb-2 block text-[10px] font-bold tracking-widest text-ink/40 uppercase">
                                    Stock
                                </label>
                                <input
                                    type="number"
                                    min="0"
                                    value={data.stock}
                                    onChange={(event) =>
                                        setData(
                                            'stock',
                                            Number(event.target.value),
                                        )
                                    }
                                    className="w-full rounded-2xl border border-ink/10 bg-white px-4 py-3 text-sm focus:border-terracotta focus:outline-none"
                                />
                                {errors.stock && (
                                    <p className="mt-2 text-[10px] text-rose-600">
                                        {errors.stock}
                                    </p>
                                )}
                            </div>
                        </div>

                        <div>
                            <label className="mb-2 block text-[10px] font-bold tracking-widest text-ink/40 uppercase">
                                Media
                            </label>
                            <input
                                type="file"
                                multiple
                                accept="image/*,video/*"
                                onChange={handleMedia}
                                className="block w-full rounded-2xl border border-ink/10 bg-white px-4 py-3 text-sm focus:border-terracotta focus:outline-none"
                            />
                            <p className="mt-2 text-[10px] text-ink-soft">
                                Add one or more images/videos. Selected files
                                will be uploaded together.
                            </p>
                            {errors.media && (
                                <p className="mt-2 text-[10px] text-rose-600">
                                    {errors.media}
                                </p>
                            )}
                            {errors['media.0'] && (
                                <p className="mt-2 text-[10px] text-rose-600">
                                    {errors['media.0']}
                                </p>
                            )}

                            {data.media.length > 0 && (
                                <div className="mt-4 rounded-2xl border border-ink/10 bg-cream/50 p-4">
                                    <div className="mb-3 text-xs font-bold tracking-widest text-ink/40 uppercase">
                                        Selected Media ({data.media.length})
                                    </div>
                                    <div className="grid gap-2">
                                        {data.media.map((file, index) => (
                                            <div
                                                key={`${file.name}-${index}`}
                                                className="flex items-center justify-between rounded-2xl border border-ink/10 bg-white px-3 py-3 text-sm"
                                            >
                                                <div className="min-w-0 overflow-hidden">
                                                    <p className="truncate font-medium text-ink">
                                                        {file.name}
                                                    </p>
                                                    <p className="text-[10px] text-ink-soft">
                                                        {file.type ||
                                                            'unknown type'}{' '}
                                                        •{' '}
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
                                                    className="text-[10px] tracking-widest text-rose-600 uppercase hover:text-rose-800"
                                                >
                                                    Remove
                                                </button>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>

                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div className="text-sm text-ink-soft">
                                The new product will be created on behalf of
                                this seller and published immediately.
                            </div>
                            <button
                                type="submit"
                                disabled={processing}
                                className="inline-flex items-center gap-2 rounded-2xl bg-ink px-5 py-3 text-xs font-bold tracking-widest text-cream uppercase transition hover:bg-terracotta disabled:opacity-50"
                            >
                                {processing ? 'Saving...' : 'Create product'}
                                <ArrowRight className="h-4 w-4" />
                            </button>
                        </div>
                    </form>
                )}
            </div>
        </div>
    );
};

AdminProductCreate.layout = (page: ReactNode) => (
    <AdminLayout children={page} />
);
export default AdminProductCreate;
