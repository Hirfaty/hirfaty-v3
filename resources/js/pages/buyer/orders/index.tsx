import { Link, router } from '@inertiajs/react';
import React from 'react';
import type { Order, PaginatedData } from '@/types';

const statusColors: Record<string, string> = {
    pending: 'bg-yellow-100 text-yellow-700',
    confirmed: 'bg-blue-100 text-blue-700',
    shipped: 'bg-purple-100 text-purple-700',
    delivered: 'bg-green-100 text-green-700',
    cancelled: 'bg-red-100 text-red-700',
};

const statusLabel: Record<string, string> = {
    pending: 'Pending',
    confirmed: 'Confirmed',
    shipped: 'Shipped',
    delivered: 'Delivered',
    cancelled: 'Cancelled',
};

const Index = ({ orders }: { orders: PaginatedData<Order> }) => {
    console.log('hello: ', orders);

    if (orders.data.length === 0) {
        return (
            <div className="flex min-h-screen flex-col items-center justify-center gap-4">
                <p className="text-gray-500">You have no orders yet.</p>
                <Link
                    href={route('products.index')}
                    className="text-sm text-blue-600 hover:underline"
                >
                    Start shopping →
                </Link>
            </div>
        );
    }

    return (
        <div className="mx-auto max-w-3xl p-8">
            <h1 className="mb-6 text-2xl font-bold">My Orders</h1>

            <div className="space-y-4">
                {orders.data.map((order) => (
                    <div key={order.id} className="border">
                        {/* Order header */}
                        <div className="flex items-center justify-between border-b bg-gray-50 px-4 py-3">
                            <div className="flex items-center gap-4">
                                <span className="font-mono text-sm font-medium">
                                    #{order.id}
                                </span>
                                <span className="text-sm text-gray-500">
                                    {order.store?.store_name}
                                </span>
                            </div>
                            <div className="flex items-center gap-4">
                                <span
                                    className={`rounded px-2 py-1 text-xs ${statusColors[order.status]}`}
                                >
                                    {statusLabel[order.status]}
                                </span>
                                <span className="text-xs text-gray-400">
                                    {new Date(
                                        order.created_at,
                                    ).toLocaleDateString()}
                                </span>
                            </div>
                        </div>

                        {/* Items preview */}
                        <div className="divide-y">
                            {order.items.map((item) => (
                                <div
                                    key={item.id}
                                    className="flex items-center gap-4 px-4 py-3"
                                >
                                    {item.product.images[0] ? (
                                        <img
                                            src={item.product.images[0].url}
                                            alt={item.product.name}
                                            className="h-12 w-12 object-cover"
                                        />
                                    ) : (
                                        <div className="h-12 w-12 bg-gray-100" />
                                    )}
                                    <div className="flex-1">
                                        <p className="text-sm font-medium">
                                            {item.product.name}
                                        </p>
                                        <p className="text-xs text-gray-400">
                                            × {item.quantity}
                                        </p>
                                    </div>
                                    <p className="text-sm font-medium">
                                        {item.subtotal} MAD
                                    </p>
                                </div>
                            ))}
                        </div>

                        {/* Order footer */}
                        <div className="flex items-center justify-between border-t px-4 py-3">
                            <div className="text-sm">
                                <span className="text-gray-500">Total: </span>
                                <span className="font-bold">
                                    {order.total_amount} MAD
                                </span>
                            </div>
                            {order.tracking_number && (
                                <p className="text-xs text-gray-400">
                                    Tracking:{' '}
                                    <span className="font-mono">
                                        {order.tracking_number}
                                    </span>
                                </p>
                            )}
                            <Link
                                href={route('buyer.orders.show', order.id)}
                                className="text-sm text-blue-600 hover:underline"
                            >
                                View details →
                            </Link>
                        </div>
                    </div>
                ))}
            </div>

            {/* Pagination */}
            <div className="mt-6 flex gap-2">
                {orders.links.map((link, i) => (
                    <button
                        key={i}
                        onClick={() => link.url && router.get(link.url)}
                        disabled={!link.url}
                        className={`border px-3 py-1 text-sm ${
                            link.active
                                ? 'bg-black text-white'
                                : 'text-gray-500 hover:bg-gray-50 disabled:opacity-40'
                        }`}
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                ))}
            </div>
        </div>
    );
};

export default Index;
