import AdminLayout from '@/layouts/admin/layout';
import { Category, PageProps } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { Edit3, Plus, Trash2 } from 'lucide-react';
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

interface CategoryWithCounts extends Category {
    products_count: number;
    stores_count: number;
    parent?: Category | null;
}

const Index = ({
    categories,
    parents,
}: PageProps & {
    categories: CategoryWithCounts[];
    parents: Category[];
}) => {
    const [editingId, setEditingId] = useState<number | null>(null);

    // ── Create form ───────────────────────────────────────────
    const createForm = useForm({
        name: '',
        parent_id: '' as string | number,
        icon: '',
    });

    const handleCreate = () => {
        createForm.post(route('admin.categories.store'), {
            onSuccess: () => createForm.reset(),
        });
    };

    // ── Edit form ─────────────────────────────────────────────
    const editForm = useForm({
        name: '',
        parent_id: '' as string | number,
        icon: '',
    });

    const startEdit = (cat: Category) => {
        setEditingId(cat.id);
        editForm.setData({
            name: cat.name,
            parent_id: cat.parent_id ?? '',
            icon: cat.icon ?? '',
        });
    };

    const handleUpdate = (id: number) => {
        editForm.patch(route('admin.categories.update', id), {
            onSuccess: () => setEditingId(null),
        });
    };

    const handleDelete = (id: number, name: string) => {
        if (confirm(`Delete "${name}"? This cannot be undone.`)) {
            router.delete(route('admin.categories.destroy', id));
        }
    };

    // Chart Data: Top 10 categories by product count
    const chartData = useMemo(() => {
        return categories
            .filter((c) => c.products_count > 0)
            .sort((a, b) => (b.products_count ?? 0) - (a.products_count ?? 0))
            .slice(0, 10)
            .map((c) => ({
                name: c.name,
                count: c.products_count,
            }));
    }, [categories]);

    // Grouping
    const parents_list = categories.filter((c) => !c.parent_id);
    const children_list = categories.filter((c) => c.parent_id);

    return (
        <div className="min-h-screen bg-cream/30 p-6 md:p-10">
            <Head title="Manage Categories" />

            <div className="mx-auto max-w-7xl">
                <header className="mb-12">
                    <p className="font-sans text-xs font-medium uppercase tracking-[0.2em] text-ink-soft">
                        Inventory Architecture
                    </p>
                    <h1 className="h-editorial mt-2 text-4xl text-ink md:text-5xl">
                        Categories & <span className="italic">Taxonomy</span>
                    </h1>
                </header>

                {/* ── Analytics Section ── */}
                {chartData.length > 0 && (
                    <section className="mb-12 border border-ink/5 bg-white p-8 shadow-sm">
                        <h2 className="font-sans text-xs font-semibold uppercase tracking-wider text-ink-soft">
                            Product Distribution
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
                                                Label
                                            </th>
                                            <th className="px-6 py-4 font-sans text-[10px] font-bold uppercase tracking-widest text-ink/40">
                                                Hierarchy
                                            </th>
                                            <th className="px-6 py-4 font-sans text-[10px] font-bold uppercase tracking-widest text-ink/40">
                                                Impact
                                            </th>
                                            <th className="px-6 py-4 font-sans text-[10px] font-bold uppercase tracking-widest text-ink/40">
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-ink/5">
                                        {[
                                            ...parents_list,
                                            ...children_list,
                                        ].map((cat) => (
                                            <tr
                                                key={cat.id}
                                                className="group transition-colors hover:bg-cream/10"
                                            >
                                                {editingId === cat.id ? (
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
                                                            <select
                                                                value={
                                                                    editForm
                                                                        .data
                                                                        .parent_id
                                                                }
                                                                onChange={(e) =>
                                                                    editForm.setData(
                                                                        'parent_id',
                                                                        e.target.value,
                                                                    )
                                                                }
                                                                className="border border-ink/10 bg-cream/5 px-3 py-2 text-sm focus:border-terracotta focus:outline-none"
                                                            >
                                                                <option value="">
                                                                    Root
                                                                </option>
                                                                {parents
                                                                    .filter(
                                                                        (p) =>
                                                                            p.id !==
                                                                            cat.id,
                                                                    )
                                                                    .map(
                                                                        (p) => (
                                                                            <option
                                                                                key={
                                                                                    p.id
                                                                                }
                                                                                value={
                                                                                    p.id
                                                                                }
                                                                            >
                                                                                {
                                                                                    p.name
                                                                                }
                                                                            </option>
                                                                        ),
                                                                    )}
                                                            </select>
                                                            <div className="flex gap-2">
                                                                <button
                                                                    onClick={() =>
                                                                        handleUpdate(
                                                                            cat.id,
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
                                                                {cat.icon && (
                                                                    <span className="flex h-8 w-8 items-center justify-center bg-cream/50 text-lg">
                                                                        {
                                                                            cat.icon
                                                                        }
                                                                    </span>
                                                                )}
                                                                <div>
                                                                    <p
                                                                        className={`text-sm ${cat.parent_id ? 'text-ink/70' : 'font-medium text-ink'}`}
                                                                    >
                                                                        {
                                                                            cat.name
                                                                        }
                                                                    </p>
                                                                    <p className="text-[10px] text-ink-soft uppercase tracking-wider">
                                                                        {
                                                                            cat.slug
                                                                        }
                                                                    </p>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td className="px-6 py-4">
                                                            {cat.parent ? (
                                                                <span className="inline-flex items-center gap-1.5 bg-ink/[0.03] px-2 py-0.5 text-[10px] font-medium text-ink-soft">
                                                                    {
                                                                        cat.parent
                                                                            .name
                                                                    }
                                                                </span>
                                                            ) : (
                                                                <span className="text-[10px] font-semibold text-terracotta uppercase tracking-widest">
                                                                    Root
                                                                </span>
                                                            )}
                                                        </td>
                                                        <td className="px-6 py-4">
                                                            <div className="flex gap-4">
                                                                <div className="text-center">
                                                                    <p className="text-xs font-bold text-ink">
                                                                        {
                                                                            cat.products_count
                                                                        }
                                                                    </p>
                                                                    <p className="text-[10px] text-ink-soft uppercase">
                                                                        Items
                                                                    </p>
                                                                </div>
                                                                <div className="text-center">
                                                                    <p className="text-xs font-bold text-ink">
                                                                        {
                                                                            cat.stores_count
                                                                        }
                                                                    </p>
                                                                    <p className="text-[10px] text-ink-soft uppercase">
                                                                        Shops
                                                                    </p>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td className="px-6 py-4">
                                                            <div className="flex items-center gap-2 opacity-0 transition-opacity group-hover:opacity-100">
                                                                <button
                                                                    onClick={() =>
                                                                        startEdit(
                                                                            cat,
                                                                        )
                                                                    }
                                                                    className="p-1.5 text-ink-soft hover:bg-ink hover:text-cream"
                                                                >
                                                                    <Edit3 className="h-3.5 w-3.5" />
                                                                </button>
                                                                <button
                                                                    onClick={() =>
                                                                        handleDelete(
                                                                            cat.id,
                                                                            cat.name,
                                                                        )
                                                                    }
                                                                    className="p-1.5 text-ink-soft hover:bg-terracotta hover:text-cream"
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
                                New <span className="italic">Category</span>
                            </h3>
                            <div className="space-y-6">
                                <div>
                                    <label className="mb-2 block text-[10px] font-bold uppercase tracking-widest text-ink/40">
                                        Nominal Label
                                    </label>
                                    <input
                                        value={createForm.data.name}
                                        onChange={(e) =>
                                            createForm.setData(
                                                'name',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="e.g. Handmade Carpets"
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
                                        Hierarchy Placement
                                    </label>
                                    <select
                                        value={createForm.data.parent_id}
                                        onChange={(e) =>
                                            createForm.setData(
                                                'parent_id',
                                                e.target.value,
                                            )
                                        }
                                        className="w-full border border-ink/10 bg-cream/5 px-4 py-3 text-sm focus:border-terracotta focus:outline-none"
                                    >
                                        <option value="">
                                            Independent (Root)
                                        </option>
                                        {parents.map((p) => (
                                            <option key={p.id} value={p.id}>
                                                {p.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div>
                                    <label className="mb-2 block text-[10px] font-bold uppercase tracking-widest text-ink/40">
                                        Visual Icon
                                    </label>
                                    <input
                                        value={createForm.data.icon}
                                        onChange={(e) =>
                                            createForm.setData(
                                                'icon',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="🧶"
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
                                        ? 'Defining...'
                                        : 'Define Category'}
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
