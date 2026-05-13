import { useForm } from '@inertiajs/react';
import type { ReactNode } from 'react';
import AdminLayout from '@/layouts/admin/layout';
import type { Category, City, PageProps } from '@/types';

const Create = ({
    cities,
    categories,
}: PageProps & { cities: City[]; categories: Category[] }) => {
    const { data, setData, post, processing, errors } = useForm({
        first_name: '',
        last_name: '',
        email: '',
        phone_number: '',
        role: 'buyer' as 'buyer' | 'seller',
        store_name: '',
        category_id: categories[0]?.id ?? null,
        city_id: cities[0]?.id ?? null,
        description: '',
    });

    console.log(errors);

    const submit = () => post(route('admin.users.store'));

    return (
        <div className="mx-auto max-w-xl p-8">
            <h1 className="mb-6 text-2xl font-bold">Create user</h1>

            <div className="space-y-5">
                {/* Role — pick first */}
                <div>
                    <label className="mb-1 block text-sm font-medium">
                        Role
                    </label>
                    <div className="flex gap-3">
                        {(['buyer', 'seller'] as const).map((r) => (
                            <button
                                key={r}
                                type="button"
                                onClick={() => setData('role', r)}
                                className={`flex-1 border py-2.5 text-sm capitalize transition-colors ${
                                    data.role === r
                                        ? 'border-black bg-black text-white'
                                        : 'border-gray-300 text-gray-500 hover:border-black'
                                }`}
                            >
                                {r}
                            </button>
                        ))}
                    </div>
                </div>

                {/* Name */}
                <div className="grid grid-cols-2 gap-4">
                    <div>
                        <label className="mb-1 block text-sm font-medium">
                            First name
                        </label>
                        <input
                            value={data.first_name}
                            onChange={(e) =>
                                setData('first_name', e.target.value)
                            }
                            className="w-full border p-2.5 text-sm focus:ring-1 focus:ring-black focus:outline-none"
                        />
                        {errors.first_name && (
                            <p className="mt-1 text-xs text-red-500">
                                {errors.first_name}
                            </p>
                        )}
                    </div>
                    <div>
                        <label className="mb-1 block text-sm font-medium">
                            Last name
                        </label>
                        <input
                            value={data.last_name}
                            onChange={(e) =>
                                setData('last_name', e.target.value)
                            }
                            className="w-full border p-2.5 text-sm focus:ring-1 focus:ring-black focus:outline-none"
                        />
                        {errors.last_name && (
                            <p className="mt-1 text-xs text-red-500">
                                {errors.last_name}
                            </p>
                        )}
                    </div>
                </div>

                {/* Email */}
                <div>
                    <label className="mb-1 block text-sm font-medium">
                        Email
                    </label>
                    <input
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        className="w-full border p-2.5 text-sm focus:ring-1 focus:ring-black focus:outline-none"
                    />
                    {errors.email && (
                        <p className="mt-1 text-xs text-red-500">
                            {errors.email}
                        </p>
                    )}
                </div>

                {/* Phone */}
                <div>
                    <label className="mb-1 block text-sm font-medium">
                        Phone{' '}
                        <span className="font-normal text-gray-400">
                            (optional)
                        </span>
                    </label>
                    <input
                        value={data.phone_number}
                        onChange={(e) =>
                            setData('phone_number', e.target.value)
                        }
                        className="w-full border p-2.5 text-sm focus:ring-1 focus:ring-black focus:outline-none"
                    />
                    {errors.phone_number && (
                        <p className="mt-1 text-xs text-red-500">
                            {errors.phone_number}
                        </p>
                    )}
                </div>

                {/* Seller-only fields */}
                {data.role === 'seller' && (
                    <div className="space-y-5 border-t pt-5">
                        <p className="text-xs tracking-wider text-gray-400 uppercase">
                            Store info
                        </p>

                        <div>
                            <label className="mb-1 block text-sm font-medium">
                                Store name
                            </label>
                            <input
                                value={data.store_name}
                                onChange={(e) =>
                                    setData('store_name', e.target.value)
                                }
                                className="w-full border p-2.5 text-sm focus:ring-1 focus:ring-black focus:outline-none"
                            />
                            {errors.store_name && (
                                <p className="mt-1 text-xs text-red-500">
                                    {errors.store_name}
                                </p>
                            )}
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="mb-1 block text-sm font-medium">
                                    Category
                                </label>
                                <select
                                    value={data.category_id ?? ''}
                                    onChange={(e) =>
                                        setData(
                                            'category_id',
                                            Number(e.target.value),
                                        )
                                    }
                                    className="w-full border p-2.5 text-sm focus:ring-1 focus:ring-black focus:outline-none"
                                >
                                    {categories.map((c) => (
                                        <option key={c.id} value={c.id}>
                                            {c.name}
                                        </option>
                                    ))}
                                </select>
                                {errors.category_id && (
                                    <p className="mt-1 text-xs text-red-500">
                                        {errors.category_id}
                                    </p>
                                )}
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium">
                                    City
                                </label>
                                <select
                                    value={data.city_id ?? ''}
                                    onChange={(e) =>
                                        setData(
                                            'city_id',
                                            Number(e.target.value),
                                        )
                                    }
                                    className="w-full border p-2.5 text-sm focus:ring-1 focus:ring-black focus:outline-none"
                                >
                                    {cities.map((c) => (
                                        <option key={c.id} value={c.id}>
                                            {c.name}
                                        </option>
                                    ))}
                                </select>
                                {errors.city_id && (
                                    <p className="mt-1 text-xs text-red-500">
                                        {errors.city_id}
                                    </p>
                                )}
                            </div>
                        </div>

                        <div>
                            <label className="mb-1 block text-sm font-medium">
                                Description{' '}
                                <span className="font-normal text-gray-400">
                                    (optional)
                                </span>
                            </label>
                            <textarea
                                value={data.description}
                                onChange={(e) =>
                                    setData('description', e.target.value)
                                }
                                rows={3}
                                className="w-full border p-2.5 text-sm focus:ring-1 focus:ring-black focus:outline-none"
                            />
                        </div>
                    </div>
                )}

                {/* Password note */}
                <div className="rounded bg-gray-50 p-4 text-xs text-gray-500">
                    A secure password will be auto-generated and sent to the
                    user's email along with their login credentials.
                </div>

                <button
                    onClick={submit}
                    disabled={processing}
                    className="w-full bg-black py-3 text-sm text-white hover:bg-gray-800 disabled:opacity-50"
                >
                    {processing ? 'Creating...' : `Create ${data.role} account`}
                </button>
            </div>
        </div>
    );
};

Create.layout = (page: ReactNode) => <AdminLayout children={page} />;
export default Create;
