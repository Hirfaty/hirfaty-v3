import AdminLayout from '@/layouts/admin/layout';
import { City, PageProps } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { Edit3, MapPin, Plus, Trash2 } from 'lucide-react';
import { ReactNode, useMemo, useState } from 'react';
import {
    Bar,
    BarChart,
    Cell,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

interface CityWithCounts extends City {
    stores_count: number;
    orders_count: number;
}

const Index = ({
    cities,
}: PageProps & {
    cities: CityWithCounts[];
}) => {
    const [editingId, setEditingId] = useState<number | null>(null);

    // ── Create form ───────────────────────────────────────────
    const createForm = useForm({
        name: '',
        region: '',
    });

    const handleCreate = () => {
        createForm.post(route('admin.cities.store'), {
            onSuccess: () => createForm.reset(),
        });
    };

    // ── Edit form ─────────────────────────────────────────────
    const editForm = useForm({
        name: '',
        region: '',
    });

    const startEdit = (city: City) => {
        setEditingId(city.id);
        editForm.setData({
            name: city.name,
            region: city.region ?? '',
        });
    };

    const handleUpdate = (id: number) => {
        editForm.patch(route('admin.cities.update', id), {
            onSuccess: () => setEditingId(null),
        });
    };

    const handleDelete = (id: number, name: string) => {
        if (confirm(`Delete "${name}"? This cannot be undone.`)) {
            router.delete(route('admin.cities.destroy', id));
        }
    };

    // Chart Data: Top 10 cities by store count
    const chartData = useMemo(() => {
        return cities
            .filter((c) => c.stores_count > 0)
            .sort((a, b) => (b.stores_count ?? 0) - (a.stores_count ?? 0))
            .slice(0, 10)
            .map((c) => ({
                name: c.name,
                count: c.stores_count,
            }));
    }, [cities]);

    return (
        <div className="min-h-full p-6 md:p-10">
            <Head title="Manage Cities" />

            <div className="mx-auto max-w-7xl">
                <header className="mb-12">
                    <p className="font-sans text-xs font-medium uppercase tracking-[0.2em] text-ink-soft">
                        Geographic Infrastructure
                    </p>
                    <h1 className="h-editorial mt-2 text-4xl text-ink md:text-5xl">
                        Cities & <span className="italic">Regions</span>
                    </h1>
                </header>

                {/* ── Analytics Section ── */}
                {chartData.length > 0 && (
                    <section className="mb-12 border border-ink/5 bg-white p-8 shadow-sm">
                        <h2 className="font-sans text-xs font-semibold uppercase tracking-wider text-ink-soft">
                            Store Distribution by City
                        </h2>
                        <div className="mt-8 h-[300px] w-full">
                            <ResponsiveContainer width="100%" height="100%">
                                <BarChart data={chartData} layout="vertical">
                                    <XAxis type="number" hide />
                                    <YAxis
                                        dataKey="name"
                                        type="category"
                                        width={120}
                                        axisLine={false}
                                        tickLine={false}
                                        style={{
                                            fontSize: '12px',
                                            fontFamily: 'Inter, sans-serif',
                                            fill: '#333',
                                        }}
                                    />
                                    <Tooltip
                                        cursor={{ fill: 'transparent' }}
                                        contentStyle={{
                                            backgroundColor: '#121212',
                                            border: 'none',
                                            color: '#F9F7F2',
                                            fontSize: '12px',
                                        }}
                                    />
                                    <Bar
                                        dataKey="count"
                                        radius={[0, 4, 4, 0]}
                                        barSize={20}
                                    >
                                        {chartData.map((_, index) => (
                                            <Cell
                                                key={`cell-${index}`}
                                                fill={
                                                    index % 2 === 0
                                                        ? '#A84C2E'
                                                        : '#121212'
                                                }
                                            />
                                        ))}
                                    </Bar>
                                </BarChart>
                            </ResponsiveContainer>
                        </div>
                    </section>
                )}

                <div className="grid grid-cols-12 items-start gap-10">
                    {/* ── Management List ── */}
                    <div className="col-span-12 lg:col-span-8">
                        <div className="border border-ink/5 bg-white shadow-sm">
                            <div className="overflow-x-auto">
                                <table className="w-full text-left">
                                    <thead>
                                        <tr className="border-b border-ink/5 bg-ink/[0.02]">
                                            <th className="px-6 py-4 font-sans text-[10px] font-bold uppercase tracking-widest text-ink/40">
                                                City Label
                                            </th>
                                            <th className="px-6 py-4 font-sans text-[10px] font-bold uppercase tracking-widest text-ink/40">
                                                Regional Context
                                            </th>
                                            <th className="px-6 py-4 font-sans text-[10px] font-bold uppercase tracking-widest text-ink/40">
                                                Activity
                                            </th>
                                            <th className="px-6 py-4 font-sans text-[10px] font-bold uppercase tracking-widest text-ink/40">
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-ink/5">
                                        {cities.map((city) => (
                                            <tr
                                                key={city.id}
                                                className="group transition-colors hover:bg-cream/10"
                                            >
                                                {editingId === city.id ? (
                                                    <td
                                                        colSpan={4}
                                                        className="px-6 py-4"
                                                    >
                                                        <div className="flex flex-wrap items-center gap-4">
                                                            <input
                                                                value={
                                                                    editForm
                                                                        .data
                                                                        .name
                                                                }
                                                                onChange={(e) =>
                                                                    editForm.setData(
                                                                        'name',
                                                                        e.target.value,
                                                                    )
                                                                }
                                                                className="flex-1 min-w-[200px] border border-ink/10 bg-cream/5 px-3 py-2 text-sm focus:border-terracotta focus:outline-none"
                                                            />
                                                            <input
                                                                value={
                                                                    editForm
                                                                        .data
                                                                        .region
                                                                }
                                                                onChange={(e) =>
                                                                    editForm.setData(
                                                                        'region',
                                                                        e.target.value,
                                                                    )
                                                                }
                                                                placeholder="Region"
                                                                className="border border-ink/10 bg-cream/5 px-3 py-2 text-sm focus:border-terracotta focus:outline-none"
                                                            />
                                                            <div className="flex gap-2">
                                                                <button
                                                                    onClick={() =>
                                                                        handleUpdate(
                                                                            city.id,
                                                                        )
                                                                    }
                                                                    disabled={
                                                                        editForm.processing
                                                                    }
                                                                    className="bg-ink px-4 py-2 text-xs font-medium text-cream hover:bg-terracotta disabled:opacity-50"
                                                                >
                                                                    Update
                                                                </button>
                                                                <button
                                                                    onClick={() =>
                                                                        setEditingId(
                                                                            null,
                                                                        )
                                                                    }
                                                                    className="px-3 py-2 text-xs font-medium text-ink-soft hover:text-ink"
                                                                >
                                                                    Cancel
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </td>
                                                ) : (
                                                    <>
                                                        <td className="px-6 py-4">
                                                            <div className="flex items-center gap-3">
                                                                <div className="flex h-8 w-8 items-center justify-center bg-cream/50 text-ink-soft">
                                                                    <MapPin className="h-4 w-4" />
                                                                </div>
                                                                <div>
                                                                    <p className="text-sm font-medium text-ink">
                                                                        {
                                                                            city.name
                                                                        }
                                                                    </p>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td className="px-6 py-4">
                                                            <span className="text-[10px] font-semibold text-terracotta uppercase tracking-widest">
                                                                {city.region ??
                                                                    'Undefined'}
                                                            </span>
                                                        </td>
                                                        <td className="px-6 py-4">
                                                            <div className="flex gap-4">
                                                                <div className="text-center">
                                                                    <p className="text-xs font-bold text-ink">
                                                                        {
                                                                            city.stores_count
                                                                        }
                                                                    </p>
                                                                    <p className="text-[10px] text-ink-soft uppercase">
                                                                        Shops
                                                                    </p>
                                                                </div>
                                                                <div className="text-center">
                                                                    <p className="text-xs font-bold text-ink">
                                                                        {
                                                                            city.orders_count
                                                                        }
                                                                    </p>
                                                                    <p className="text-[10px] text-ink-soft uppercase">
                                                                        Orders
                                                                    </p>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td className="px-6 py-4">
                                                            <div className="flex items-center gap-2 opacity-0 transition-opacity group-hover:opacity-100">
                                                                <button
                                                                    onClick={() =>
                                                                        startEdit(
                                                                            city,
                                                                        )
                                                                    }
                                                                    className="p-1.5 text-ink-soft hover:bg-ink hover:text-cream"
                                                                >
                                                                    <Edit3 className="h-3.5 w-3.5" />
                                                                </button>
                                                                <button
                                                                    onClick={() =>
                                                                        handleDelete(
                                                                            city.id,
                                                                            city.name,
                                                                        )
                                                                    }
                                                                    disabled={
                                                                        city.stores_count >
                                                                            0 ||
                                                                        city.orders_count >
                                                                            0
                                                                    }
                                                                    className="p-1.5 text-ink-soft hover:bg-terracotta hover:text-cream disabled:opacity-30 disabled:hover:bg-transparent disabled:hover:text-ink-soft"
                                                                >
                                                                    <Trash2 className="h-3.5 w-3.5" />
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </>
                                                )}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    {/* ── Create Sidebar ── */}
                    <div className="col-span-12 lg:col-span-4">
                        <div className="sticky top-10 border border-ink/5 bg-white p-8 shadow-sm">
                            <h3 className="h-editorial mb-6 text-2xl text-ink">
                                New <span className="italic">City</span>
                            </h3>
                            <div className="space-y-6">
                                <div>
                                    <label className="mb-2 block text-[10px] font-bold uppercase tracking-widest text-ink/40">
                                        City Name
                                    </label>
                                    <input
                                        value={createForm.data.name}
                                        onChange={(e) =>
                                            createForm.setData(
                                                'name',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="e.g. Marrakech"
                                        className="w-full border border-ink/10 bg-cream/5 px-4 py-3 text-sm focus:border-terracotta focus:outline-none placeholder:text-ink/20"
                                    />
                                    {createForm.errors.name && (
                                        <p className="mt-2 text-[10px] text-terracotta uppercase tracking-wider">
                                            {createForm.errors.name}
                                        </p>
                                    )}
                                </div>

                                <div>
                                    <label className="mb-2 block text-[10px] font-bold uppercase tracking-widest text-ink/40">
                                        Regional Identity
                                    </label>
                                    <input
                                        value={createForm.data.region}
                                        onChange={(e) =>
                                            createForm.setData(
                                                'region',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="e.g. Marrakech-Safi"
                                        className="w-full border border-ink/10 bg-cream/5 px-4 py-3 text-sm focus:border-terracotta focus:outline-none placeholder:text-ink/20"
                                    />
                                </div>

                                <button
                                    onClick={handleCreate}
                                    disabled={
                                        createForm.processing ||
                                        !createForm.data.name
                                    }
                                    className="group flex w-full items-center justify-center gap-2 bg-ink py-4 text-xs font-bold uppercase tracking-widest text-cream transition-colors hover:bg-terracotta disabled:opacity-50"
                                >
                                    <Plus className="h-4 w-4" />
                                    {createForm.processing
                                        ? 'Recording...'
                                        : 'Record City'}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};

Index.layout = (page: ReactNode) => <AdminLayout children={page} />;
export default Index;
