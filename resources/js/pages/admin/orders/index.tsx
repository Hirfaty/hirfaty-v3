import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowUpRight,
    Filter,
    Package,
    Search,
    ShoppingBag,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import AdminLayout from '@/layouts/admin/layout';
import type { Order, PageProps } from '@/types';

const Index = ({
    orders,
    filters,
}: PageProps & {
    orders: {
        data: Order[];
        links: any[];
        meta: any;
    };
    filters: {
        search?: string;
        status?: string;
    };
}) => {
    const [search, setSearch] = useState(filters.search || '');

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            route('admin.orders.index'),
            { search, status: filters.status },
            { preserveState: true },
        );
    };

    const handleStatusFilter = (status: string) => {
        router.get(
            route('admin.orders.index'),
            { search, status },
            { preserveState: true },
        );
    };

    const getStatusColor = (status: string) => {
        switch (status) {
            case 'pending':
                return 'bg-ink/5 text-ink-soft';
            case 'confirmed':
                return 'bg-terracotta/10 text-terracotta';
            case 'shipped':
                return 'bg-blue-50 text-blue-600';
            case 'delivered':
                return 'bg-green-50 text-green-600';
            case 'cancelled':
                return 'bg-red-50 text-red-600';
            default:
                return 'bg-ink/5 text-ink-soft';
        }
    };

    return (
        <div className="min-h-screen bg-cream/30 p-6 md:p-10">
            <Head title="Order Management" />

            <div className="mx-auto max-w-7xl">
                <header className="mb-12 flex flex-wrap items-end justify-between gap-6">
                    <div>
                        <p className="font-sans text-xs font-medium tracking-[0.2em] text-ink-soft uppercase">
                            Logistics Oversight
                        </p>
                        <h1 className="h-editorial mt-2 text-4xl text-ink md:text-5xl">
                            Marketplace <span className="italic">Orders</span>
                        </h1>
                    </div>

                    <div className="flex flex-wrap items-center gap-4">
                        <form
                            onSubmit={handleSearch}
                            className="relative min-w-[300px]"
                        >
                            <Search className="absolute top-1/2 left-4 h-4 w-4 -translate-y-1/2 text-ink/30" />
                            <input
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Search ID, Tracking, Artisan..."
                                className="w-full border border-ink/10 bg-white py-3 pr-4 pl-12 text-xs font-medium tracking-widest uppercase focus:border-terracotta focus:outline-none"
                            />
                        </form>

                        <div className="flex gap-2">
                            {[
                                { id: '', label: 'All' },
                                { id: 'pending', label: 'Pending' },
                                { id: 'confirmed', label: 'Confirmed' },
                                { id: 'shipped', label: 'Shipped' },
                                { id: 'delivered', label: 'Delivered' },
                            ].map((s) => (
                                <button
                                    key={s.id}
                                    onClick={() => handleStatusFilter(s.id)}
                                    className={`border px-4 py-3 text-[10px] font-bold tracking-widest uppercase transition-all ${
                                        filters.status === s.id
                                            ? 'border-ink bg-ink text-cream'
                                            : 'border-ink/10 bg-white text-ink-soft hover:border-ink'
                                    }`}
                                >
                                    {s.label}
                                </button>
                            ))}
                        </div>
                    </div>
                </header>

                <div className="overflow-hidden border border-ink/5 bg-white shadow-sm">
                    <table className="w-full text-left">
                        <thead>
                            <tr className="border-b border-ink/5 bg-ink/[0.02]">
                                <th className="px-8 py-5 font-sans text-[10px] font-bold tracking-widest text-ink/40 uppercase">
                                    Order Sequence
                                </th>
                                <th className="px-8 py-5 font-sans text-[10px] font-bold tracking-widest text-ink/40 uppercase">
                                    Entity Relationship
                                </th>
                                <th className="px-8 py-5 font-sans text-[10px] font-bold tracking-widest text-ink/40 uppercase">
                                    Financial Impact
                                </th>
                                <th className="px-8 py-5 font-sans text-[10px] font-bold tracking-widest text-ink/40 uppercase">
                                    Status
                                </th>
                                <th className="px-8 py-5 font-sans text-[10px] font-bold tracking-widest text-ink/40 uppercase"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-ink/5 text-sm">
                            {orders.data.map((order) => (
                                <tr
                                    key={order.id}
                                    className="group transition-colors hover:bg-cream/5"
                                >
                                    <td className="px-8 py-6">
                                        <p className="font-sans text-xs font-bold text-ink">
                                            #{order.id}
                                        </p>
                                        <p className="mt-1 text-[10px] tracking-wider text-ink-soft uppercase">
                                            {new Date(
                                                order.created_at as string,
                                            ).toLocaleDateString()}
                                        </p>
                                    </td>
                                    <td className="px-8 py-6">
                                        <div className="flex flex-col">
                                            <span className="text-xs font-medium text-ink">
                                                {order.buyer?.first_name}{' '}
                                                {order.buyer?.last_name}
                                            </span>
                                            <span className="mt-1 flex items-center gap-1.5 text-[10px] tracking-widest text-ink-soft uppercase">
                                                <ShoppingBag className="h-2.5 w-2.5" />
                                                {order.store?.store_name}
                                            </span>
                                        </div>
                                    </td>
                                    <td className="px-8 py-6">
                                        <p className="text-xs font-bold text-ink">
                                            {new Intl.NumberFormat('fr-MA', {
                                                style: 'currency',
                                                currency: 'MAD',
                                            }).format(
                                                Number(order.total_amount),
                                            )}
                                        </p>
                                        <p className="mt-1 text-[10px] tracking-wider text-ink-soft uppercase">
                                            Net:{' '}
                                            {new Intl.NumberFormat('fr-MA', {
                                                style: 'currency',
                                                currency: 'MAD',
                                            }).format(
                                                Number(order.seller_earnings),
                                            )}
                                        </p>
                                    </td>
                                    <td className="px-8 py-6">
                                        <span
                                            className={`inline-block rounded-full px-3 py-1 text-[8px] font-bold tracking-widest uppercase ${getStatusColor(
                                                order.status,
                                            )}`}
                                        >
                                            {order.status}
                                        </span>
                                    </td>
                                    <td className="px-8 py-6 text-right">
                                        <Link
                                            href={route(
                                                'admin.orders.show',
                                                order.id,
                                            )}
                                            className="inline-flex h-8 w-8 items-center justify-center border border-ink/5 bg-cream/20 text-ink-soft transition-all hover:border-ink hover:bg-ink hover:text-cream"
                                        >
                                            <ArrowUpRight className="h-4 w-4" />
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>

                    {orders.data.length === 0 && (
                        <div className="py-20 text-center">
                            <Package className="mx-auto mb-4 h-12 w-12 text-ink/10" />
                            <p className="h-editorial text-xl text-ink">
                                No records found
                            </p>
                            <p className="mt-2 text-xs tracking-widest text-ink-soft uppercase">
                                Adjust filters to broaden your search
                            </p>
                        </div>
                    )}
                </div>

                {/* Simple Pagination */}
                {orders.meta && orders.meta.last_page > 1 && (
                    <div className="mt-10 flex justify-center gap-2">
                        {orders.meta.links.map((link: any, i: number) => (
                            <Link
                                key={i}
                                href={link.url}
                                className={`border px-4 py-2 text-[10px] font-bold tracking-widest uppercase transition-all ${
                                    link.active
                                        ? 'border-ink bg-ink text-cream'
                                        : 'border-ink/10 bg-white text-ink-soft hover:border-ink'
                                }`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
};

Index.layout = (page: ReactNode) => <AdminLayout children={page} />;
export default Index;
