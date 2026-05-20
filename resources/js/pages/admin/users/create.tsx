import AdminLayout from '@/layouts/admin/layout';
import type { Category, City, PageProps } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import {
    ArrowRight,
    CheckCircle2,
    Plus,
    Trash2,
    Upload,
    UserPlus,
} from 'lucide-react';
import { ReactNode, useState } from 'react';
import { toast } from 'sonner';
import axios from 'axios';

interface ProductForm {
    name: string;
    description: string;
    base_price: string;
    sale_price: string;
    stock: string;
    category_id: number | string;
    media: File[];
}

const Create = ({
    cities,
    categories,
}: PageProps & { cities: City[]; categories: Category[] }) => {
    const [step, setStep] = useState<'user' | 'products' | 'success'>('user');
    const [storeId, setStoreId] = useState<number | null>(null);

    // ── Step 1: User & Store Form ──────────────────────────────
    const userForm = useForm({
        step: 'user',
        first_name: '',
        last_name: '',
        email: '',
        phone_number: '',
        role: 'seller' as 'buyer' | 'seller',
        store_name: '',
        category_id: categories[0]?.id ?? '',
        city_id: cities[0]?.id ?? '',
        description: '',
    });

    // ── Step 2: Products Management ───────────────────────────
    const [products, setProducts] = useState<ProductForm[]>([
        {
            name: '',
            description: '',
            base_price: '',
            sale_price: '',
            stock: '10',
            category_id: categories[0]?.id ?? '',
            media: [],
        },
    ]);
    const [isSubmittingProducts, setIsSubmittingProducts] = useState(false);

    const handleUserSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        userForm.post(route('admin.users.store'), {
            onSuccess: (page) => {
                const response = page.props.flash as any;
                // Since the controller returns JSON, Inertia might wrap it or we might need to handle it via axios if we want pure data.
                // However, the controller is returning response()->json().
                // Let's use axios for the wizard steps to have better control over the data flow.
            },
            onError: () => toast.error('Please fix the errors below.'),
        });
    };

    // Alternative: Use Axios for better wizard data handling
    const submitUserStep = async () => {
        try {
            const response = await axios.post(
                route('admin.users.store'),
                userForm.data,
            );
            if (response.data.user_id) {
                if (userForm.data.role === 'seller') {
                    setStoreId(response.data.store_id);
                    setStep('products');
                    toast.success('Seller account created. Now add products.');
                } else {
                    setStep('success');
                }
            }
        } catch (error: any) {
            if (error.response?.data?.errors) {
                userForm.setError(error.response.data.errors);
            }
            toast.error('Failed to create user.');
        }
    };

    const addProductField = () => {
        setProducts([
            ...products,
            {
                name: '',
                description: '',
                base_price: '',
                sale_price: '',
                stock: '10',
                category_id: categories[0]?.id ?? '',
                media: [],
            },
        ]);
    };

    const removeProductField = (index: number) => {
        setProducts(products.filter((_, i) => i !== index));
    };

    const updateProduct = (
        index: number,
        field: keyof ProductForm,
        value: any,
    ) => {
        const newProducts = [...products];
        newProducts[index] = { ...newProducts[index], [field]: value };
        setProducts(newProducts);
    };

    const submitProducts = async () => {
        setIsSubmittingProducts(true);
        const formData = new FormData();
        formData.append('step', 'product');
        formData.append('store_id', String(storeId));

        products.forEach((p, i) => {
            formData.append(`products[${i}][name]`, p.name);
            formData.append(`products[${i}][description]`, p.description);
            formData.append(`products[${i}][base_price]`, p.base_price);
            formData.append(`products[${i}][sale_price]`, p.sale_price);
            formData.append(`products[${i}][stock]`, p.stock);
            formData.append(
                `products[${i}][category_id]`,
                String(p.category_id),
            );
            p.media.forEach((file, mIndex) => {
                formData.append(`products[${i}][media][${mIndex}]`, file);
            });
        });

        try {
            await axios.post(route('admin.users.store'), formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            setStep('success');
            toast.success('All products published successfully.');
        } catch (error) {
            toast.error('Failed to publish products.');
        } finally {
            setIsSubmittingProducts(false);
        }
    };

    return (
        <div className="min-h-screen bg-cream/30 p-6 md:p-10">
            <Head title="Onboard Artisan" />

            <div className="mx-auto max-w-4xl">
                <header className="mb-12 text-center">
                    <p className="font-sans text-xs font-medium tracking-[0.2em] text-ink-soft uppercase">
                        Platform Expansion
                    </p>
                    <h1 className="h-editorial mt-2 text-4xl text-ink md:text-5xl">
                        {step === 'user' ? (
                            <>
                                Create New <span className="italic">User</span>
                            </>
                        ) : step === 'products' ? (
                            <>
                                Initial{' '}
                                <span className="italic">Inventory</span>
                            </>
                        ) : (
                            <>
                                Onboarding{' '}
                                <span className="italic">Complete</span>
                            </>
                        )}
                    </h1>

                    {/* Progress Indicator */}
                    <div className="mt-8 flex justify-center gap-4">
                        {[
                            { id: 'user', label: 'Identity' },
                            { id: 'products', label: 'Inventory' },
                            { id: 'success', label: 'Finalize' },
                        ].map((s, i) => (
                            <div key={s.id} className="flex items-center gap-4">
                                <div
                                    className={`flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold transition-colors ${
                                        step === s.id
                                            ? 'bg-ink text-cream'
                                            : 'bg-ink/5 text-ink-soft'
                                    }`}
                                >
                                    {i + 1}
                                </div>
                                <span
                                    className={`text-[10px] font-bold tracking-widest uppercase ${
                                        step === s.id
                                            ? 'text-ink'
                                            : 'text-ink-soft'
                                    }`}
                                >
                                    {s.label}
                                </span>
                                {i < 2 && (
                                    <div className="h-px w-8 bg-ink/10" />
                                )}
                            </div>
                        ))}
                    </div>
                </header>

                <div className="border border-ink/5 bg-white p-8 shadow-sm md:p-12">
                    {/* ── STEP 1: USER DETAILS ── */}
                    {step === 'user' && (
                        <div className="animate-in duration-500 fade-in slide-in-from-bottom-4">
                            <div className="grid grid-cols-1 gap-10 md:grid-cols-2">
                                <div className="space-y-6">
                                    <h2 className="font-sans text-xs font-bold tracking-widest text-ink-soft uppercase">
                                        Personal Credentials
                                    </h2>
                                    <div className="grid grid-cols-2 gap-4">
                                        <div>
                                            <label className="mb-2 block text-[10px] font-bold tracking-widest text-ink/40 uppercase">
                                                First Name
                                            </label>
                                            <input
                                                value={userForm.data.first_name}
                                                onChange={(e) =>
                                                    userForm.setData(
                                                        'first_name',
                                                        e.target.value,
                                                    )
                                                }
                                                className="w-full border border-ink/10 bg-cream/5 px-4 py-3 text-sm focus:border-terracotta focus:outline-none"
                                            />
                                            {userForm.errors.first_name && (
                                                <p className="mt-1 text-[10px] text-terracotta uppercase">
                                                    {userForm.errors.first_name}
                                                </p>
                                            )}
                                        </div>
                                        <div>
                                            <label className="mb-2 block text-[10px] font-bold tracking-widest text-ink/40 uppercase">
                                                Last Name
                                            </label>
                                            <input
                                                value={userForm.data.last_name}
                                                onChange={(e) =>
                                                    userForm.setData(
                                                        'last_name',
                                                        e.target.value,
                                                    )
                                                }
                                                className="w-full border border-ink/10 bg-cream/5 px-4 py-3 text-sm focus:border-terracotta focus:outline-none"
                                            />
                                        </div>
                                    </div>
                                    <div>
                                        <label className="mb-2 block text-[10px] font-bold tracking-widest text-ink/40 uppercase">
                                            Electronic Mail
                                        </label>
                                        <input
                                            type="email"
                                            value={userForm.data.email}
                                            onChange={(e) =>
                                                userForm.setData(
                                                    'email',
                                                    e.target.value,
                                                )
                                            }
                                            className="w-full border border-ink/10 bg-cream/5 px-4 py-3 text-sm focus:border-terracotta focus:outline-none"
                                        />
                                        {userForm.errors.email && (
                                            <p className="mt-1 text-[10px] text-terracotta uppercase">
                                                {userForm.errors.email}
                                            </p>
                                        )}
                                    </div>
                                    <div>
                                        <label className="mb-2 block text-[10px] font-bold tracking-widest text-ink/40 uppercase">
                                            Phone Number
                                        </label>
                                        <input
                                            value={userForm.data.phone_number}
                                            onChange={(e) =>
                                                userForm.setData(
                                                    'phone_number',
                                                    e.target.value,
                                                )
                                            }
                                            className="w-full border border-ink/10 bg-cream/5 px-4 py-3 text-sm focus:border-terracotta focus:outline-none"
                                        />
                                    </div>
                                    <div>
                                        <label className="mb-3 block text-[10px] font-bold tracking-widest text-ink/40 uppercase">
                                            Role Designation
                                        </label>
                                        <div className="flex gap-2">
                                            {(['buyer', 'seller'] as const).map(
                                                (r) => (
                                                    <button
                                                        key={r}
                                                        type="button"
                                                        onClick={() =>
                                                            userForm.setData(
                                                                'role',
                                                                r,
                                                            )
                                                        }
                                                        className={`flex-1 border px-4 py-3 text-[10px] font-bold tracking-widest uppercase transition-all ${
                                                            userForm.data
                                                                .role === r
                                                                ? 'border-ink bg-ink text-cream'
                                                                : 'border-ink/10 text-ink-soft hover:border-ink'
                                                        }`}
                                                    >
                                                        {r}
                                                    </button>
                                                ),
                                            )}
                                        </div>
                                    </div>
                                </div>

                                <div className="space-y-6">
                                    {userForm.data.role === 'seller' ? (
                                        <>
                                            <h2 className="font-sans text-xs font-bold tracking-widest text-ink-soft uppercase">
                                                Store Identity
                                            </h2>
                                            <div>
                                                <label className="mb-2 block text-[10px] font-bold tracking-widest text-ink/40 uppercase">
                                                    Artisan Shop Name
                                                </label>
                                                <input
                                                    value={
                                                        userForm.data.store_name
                                                    }
                                                    onChange={(e) =>
                                                        userForm.setData(
                                                            'store_name',
                                                            e.target.value,
                                                        )
                                                    }
                                                    className="w-full border border-ink/10 bg-cream/5 px-4 py-3 text-sm focus:border-terracotta focus:outline-none"
                                                />
                                                {userForm.errors.store_name && (
                                                    <p className="mt-1 text-[10px] text-terracotta uppercase">
                                                        {
                                                            userForm.errors
                                                                .store_name
                                                        }
                                                    </p>
                                                )}
                                            </div>
                                            <div className="grid grid-cols-2 gap-4">
                                                <div>
                                                    <label className="mb-2 block text-[10px] font-bold tracking-widest text-ink/40 uppercase">
                                                        Trade Category
                                                    </label>
                                                    <select
                                                        value={
                                                            userForm.data
                                                                .category_id
                                                        }
                                                        onChange={(e) =>
                                                            userForm.setData(
                                                                'category_id',
                                                                e.target.value,
                                                            )
                                                        }
                                                        className="w-full border border-ink/10 bg-cream/5 px-4 py-3 text-sm focus:border-terracotta focus:outline-none"
                                                    >
                                                        {categories.map((c) => (
                                                            <option
                                                                key={c.id}
                                                                value={c.id}
                                                            >
                                                                {c.name}
                                                            </option>
                                                        ))}
                                                    </select>
                                                </div>
                                                <div>
                                                    <label className="mb-2 block text-[10px] font-bold tracking-widest text-ink/40 uppercase">
                                                        City Hub
                                                    </label>
                                                    <select
                                                        value={
                                                            userForm.data
                                                                .city_id
                                                        }
                                                        onChange={(e) =>
                                                            userForm.setData(
                                                                'city_id',
                                                                e.target.value,
                                                            )
                                                        }
                                                        className="w-full border border-ink/10 bg-cream/5 px-4 py-3 text-sm focus:border-terracotta focus:outline-none"
                                                    >
                                                        {cities.map((c) => (
                                                            <option
                                                                key={c.id}
                                                                value={c.id}
                                                            >
                                                                {c.name}
                                                            </option>
                                                        ))}
                                                    </select>
                                                </div>
                                            </div>
                                            <div>
                                                <label className="mb-2 block text-[10px] font-bold tracking-widest text-ink/40 uppercase">
                                                    Artisan Story
                                                </label>
                                                <textarea
                                                    rows={4}
                                                    value={
                                                        userForm.data
                                                            .description
                                                    }
                                                    onChange={(e) =>
                                                        userForm.setData(
                                                            'description',
                                                            e.target.value,
                                                        )
                                                    }
                                                    className="w-full border border-ink/10 bg-cream/5 px-4 py-3 text-sm focus:border-terracotta focus:outline-none"
                                                />
                                            </div>
                                        </>
                                    ) : (
                                        <div className="flex h-full flex-col justify-center rounded-lg border border-dashed border-ink/5 bg-cream/20 p-8 text-center">
                                            <UserPlus className="mx-auto mb-4 h-12 w-12 text-ink/20" />
                                            <p className="h-editorial text-xl text-ink">
                                                Standard Buyer Profile
                                            </p>
                                            <p className="mt-2 text-xs leading-relaxed text-ink-soft">
                                                Buyers can browse the
                                                marketplace, manage their cart,
                                                and track orders instantly. No
                                                store details required.
                                            </p>
                                        </div>
                                    )}
                                </div>
                            </div>

                            <div className="mt-12 flex items-center justify-between border-t border-ink/5 pt-8">
                                <div className="max-w-[200px] text-[10px] leading-tight text-ink-soft">
                                    Credentials will be auto-generated and sent
                                    to the user's electronic mail.
                                </div>
                                <button
                                    onClick={submitUserStep}
                                    disabled={
                                        userForm.processing ||
                                        !userForm.data.email
                                    }
                                    className="group flex items-center gap-3 bg-ink px-8 py-4 text-xs font-bold tracking-widest text-cream uppercase transition-colors hover:bg-terracotta disabled:opacity-50"
                                >
                                    {userForm.processing
                                        ? 'Synchronizing...'
                                        : 'Proceed to Next Step'}
                                    <ArrowRight className="h-4 w-4 transition-transform group-hover:translate-x-1" />
                                </button>
                            </div>
                        </div>
                    )}

                    {/* ── STEP 2: PRODUCT SELECTION ── */}
                    {step === 'products' && (
                        <div className="animate-in duration-500 fade-in slide-in-from-bottom-4">
                            <div className="mb-10 flex items-end justify-between">
                                <div>
                                    <h2 className="font-sans text-xs font-bold tracking-widest text-ink-soft uppercase">
                                        Initial Catalog
                                    </h2>
                                    <p className="h-editorial mt-1 text-2xl text-ink">
                                        Showcase the Artisan's{' '}
                                        <span className="italic">Work</span>
                                    </p>
                                </div>
                                <button
                                    onClick={addProductField}
                                    className="flex items-center gap-2 border border-ink/10 bg-white px-4 py-2 text-[10px] font-bold tracking-widest text-ink uppercase transition-colors hover:bg-ink hover:text-cream"
                                >
                                    <Plus className="h-3.5 w-3.5" />
                                    Add Another Product
                                </button>
                            </div>

                            <div className="space-y-8">
                                {products.map((p, index) => (
                                    <div
                                        key={index}
                                        className="relative border border-ink/10 bg-cream/5 p-6 md:p-8"
                                    >
                                        <button
                                            onClick={() =>
                                                removeProductField(index)
                                            }
                                            disabled={products.length === 1}
                                            className="absolute top-4 right-4 p-2 text-ink-soft hover:text-terracotta disabled:opacity-0"
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </button>

                                        <div className="grid grid-cols-1 gap-8 md:grid-cols-12">
                                            {/* Media Upload */}
                                            <div className="md:col-span-4">
                                                <label className="mb-2 block text-[10px] font-bold tracking-widest text-ink/40 uppercase">
                                                    Product Visuals
                                                </label>
                                                <div className="group relative flex aspect-square flex-col items-center justify-center border border-ink/10 bg-white transition-colors hover:border-terracotta">
                                                    {p.media.length > 0 ? (
                                                        <div className="grid h-full w-full grid-cols-2 gap-1 p-1">
                                                            {p.media.map(
                                                                (f, mi) => (
                                                                    <div
                                                                        key={mi}
                                                                        className="h-full w-full overflow-hidden bg-cream/50"
                                                                    >
                                                                        <img
                                                                            src={URL.createObjectURL(
                                                                                f,
                                                                            )}
                                                                            className="h-full w-full object-cover"
                                                                        />
                                                                    </div>
                                                                ),
                                                            )}
                                                            <label className="absolute inset-0 flex cursor-pointer items-center justify-center bg-ink/60 opacity-0 transition-opacity group-hover:opacity-100">
                                                                <Upload className="h-6 w-6 text-cream" />
                                                                <input
                                                                    type="file"
                                                                    multiple
                                                                    className="hidden"
                                                                    onChange={(
                                                                        e,
                                                                    ) => {
                                                                        if (
                                                                            e
                                                                                .target
                                                                                .files
                                                                        ) {
                                                                            updateProduct(
                                                                                index,
                                                                                'media',
                                                                                [
                                                                                    ...p.media,
                                                                                    ...Array.from(
                                                                                        e
                                                                                            .target
                                                                                            .files,
                                                                                    ),
                                                                                ],
                                                                            );
                                                                        }
                                                                    }}
                                                                />
                                                            </label>
                                                        </div>
                                                    ) : (
                                                        <label className="flex h-full w-full cursor-pointer flex-col items-center justify-center">
                                                            <Upload className="mb-2 h-6 w-6 text-ink/20" />
                                                            <span className="text-[10px] font-bold tracking-widest text-ink/40 uppercase">
                                                                Upload Images
                                                            </span>
                                                            <input
                                                                type="file"
                                                                multiple
                                                                className="hidden"
                                                                onChange={(
                                                                    e,
                                                                ) => {
                                                                    if (
                                                                        e.target
                                                                            .files
                                                                    ) {
                                                                        updateProduct(
                                                                            index,
                                                                            'media',
                                                                            Array.from(
                                                                                e
                                                                                    .target
                                                                                    .files,
                                                                            ),
                                                                        );
                                                                    }
                                                                }}
                                                            />
                                                        </label>
                                                    )}
                                                </div>
                                            </div>

                                            {/* Details */}
                                            <div className="space-y-4 md:col-span-8">
                                                <div className="grid grid-cols-2 gap-4">
                                                    <div className="col-span-2">
                                                        <label className="mb-2 block text-[10px] font-bold tracking-widest text-ink/40 uppercase">
                                                            Product Name
                                                        </label>
                                                        <input
                                                            value={p.name}
                                                            onChange={(e) =>
                                                                updateProduct(
                                                                    index,
                                                                    'name',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            className="w-full border border-ink/10 bg-white px-4 py-2.5 text-sm focus:border-terracotta focus:outline-none"
                                                        />
                                                    </div>
                                                    <div>
                                                        <label className="mb-2 block text-[10px] font-bold tracking-widest text-ink/40 uppercase">
                                                            Base Price (DH)
                                                        </label>
                                                        <input
                                                            type="number"
                                                            value={p.base_price}
                                                            onChange={(e) =>
                                                                updateProduct(
                                                                    index,
                                                                    'base_price',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            className="w-full border border-ink/10 bg-white px-4 py-2.5 text-sm focus:border-terracotta focus:outline-none"
                                                        />
                                                    </div>
                                                    <div>
                                                        <label className="mb-2 block text-[10px] font-bold tracking-widest text-ink/40 uppercase">
                                                            Stock Level
                                                        </label>
                                                        <input
                                                            type="number"
                                                            value={p.stock}
                                                            onChange={(e) =>
                                                                updateProduct(
                                                                    index,
                                                                    'stock',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            className="w-full border border-ink/10 bg-white px-4 py-2.5 text-sm focus:border-terracotta focus:outline-none"
                                                        />
                                                    </div>
                                                    <div className="col-span-2">
                                                        <label className="mb-2 block text-[10px] font-bold tracking-widest text-ink/40 uppercase">
                                                            Description
                                                        </label>
                                                        <textarea
                                                            rows={2}
                                                            value={
                                                                p.description
                                                            }
                                                            onChange={(e) =>
                                                                updateProduct(
                                                                    index,
                                                                    'description',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            className="w-full border border-ink/10 bg-white px-4 py-2.5 text-sm focus:border-terracotta focus:outline-none"
                                                        />
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>

                            <div className="mt-12 flex items-center justify-between border-t border-ink/5 pt-8">
                                <button
                                    onClick={() => setStep('success')}
                                    className="text-xs font-bold tracking-widest text-ink-soft uppercase hover:text-ink"
                                >
                                    Skip for now
                                </button>
                                <button
                                    onClick={submitProducts}
                                    disabled={
                                        isSubmittingProducts ||
                                        products.some((p) => !p.name)
                                    }
                                    className="group flex items-center gap-3 bg-ink px-8 py-4 text-xs font-bold tracking-widest text-cream uppercase transition-colors hover:bg-terracotta disabled:opacity-50"
                                >
                                    {isSubmittingProducts
                                        ? 'Publishing Catalog...'
                                        : 'Complete Onboarding'}
                                    <ArrowRight className="h-4 w-4 transition-transform group-hover:translate-x-1" />
                                </button>
                            </div>
                        </div>
                    )}

                    {/* ── STEP 3: SUCCESS ── */}
                    {step === 'success' && (
                        <div className="animate-in py-12 text-center duration-700 fade-in zoom-in">
                            <CheckCircle2 className="mx-auto mb-8 h-20 w-20 text-terracotta" />
                            <h2 className="h-editorial text-4xl text-ink">
                                Artisan Successfully{' '}
                                <span className="italic">Onboarded</span>
                            </h2>
                            <p className="mx-auto mt-6 max-w-md text-sm leading-relaxed text-ink-soft">
                                The account is active. The artisan has received
                                their credentials via email and can now manage
                                their store directly.
                            </p>
                            <div className="mt-12 flex justify-center gap-4">
                                <button
                                    onClick={() =>
                                        (window.location.href =
                                            route('admin.users.index'))
                                    }
                                    className="bg-ink px-8 py-4 text-xs font-bold tracking-widest text-cream uppercase transition-colors hover:bg-terracotta"
                                >
                                    View Users Directory
                                </button>
                                <button
                                    onClick={() => window.location.reload()}
                                    className="border border-ink/10 px-8 py-4 text-xs font-bold tracking-widest text-ink uppercase transition-colors hover:bg-ink/5"
                                >
                                    Add Another Artisan
                                </button>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
};

Create.layout = (page: ReactNode) => <AdminLayout children={page} />;
export default Create;
