import SellerLayout from '@/layouts/seller/layout';
import { Order, PaginatedData } from '@/types';
import { Link } from '@inertiajs/react';
import { router } from '@inertiajs/react';

const Index = ({ pageProps }: { pageProps: PaginatedData<Order> }) => {
    const updateStatus = (
        orderId: number,
        status: string,
        trackingNumber?: string,
    ) => {
        router.patch(route('seller.orders.status', orderId), {
            status,
            tracking_number: trackingNumber ?? null,
        });
    };

    const handleShipped = (orderId: number) => {
        const tracking = prompt('Enter tracking number:');
        if (tracking) updateStatus(orderId, 'shipped', tracking);
    };

    console.log('hello orders: ', pageProps);
    return (
        <div className="p-8">
            <h1 className="mb-6 text-2xl font-bold">Orders</h1>

            <table className="w-full border-collapse text-sm">
                <thead>
                    <tr className="border-b text-left text-xs text-gray-500 uppercase">
                        <th className="pr-4 pb-3">ID</th>
                        <th className="pr-4 pb-3">Buyer</th>
                        <th className="pr-4 pb-3">Total</th>
                        <th className="pr-4 pb-3">Status</th>
                        <th className="pr-4 pb-3">Date</th>
                        <th className="pb-3">Actions</th>
                    </tr>
                </thead>
                <tbody className="divide-y">
                    {pageProps.data.map((order) => (
                        <tr key={order.id}>
                            <td className="py-3 pr-4">#{order.id}</td>
                            <td className="py-3 pr-4">
                                {order.buyer.first_name} {order.buyer.last_name}
                            </td>
                            <td className="py-3 pr-4">
                                {order.total_amount} MAD
                            </td>
                            <td className="py-3 pr-4">
                                <span
                                    className={`rounded px-2 py-1 text-xs ${
                                        order.status === 'pending'
                                            ? 'bg-yellow-100 text-yellow-700'
                                            : order.status === 'confirmed'
                                              ? 'bg-blue-100 text-blue-700'
                                              : order.status === 'shipped'
                                                ? 'bg-purple-100 text-purple-700'
                                                : order.status === 'delivered'
                                                  ? 'bg-green-100 text-green-700'
                                                  : 'bg-red-100 text-red-700'
                                    }`}
                                >
                                    {order.status}
                                </span>
                            </td>
                            <td className="py-3 pr-4">
                                {new Date(
                                    order.created_at,
                                ).toLocaleDateString()}
                            </td>
                            <td className="py-3">
                                <div className="flex items-center gap-2">
                                    <Link
                                        href={route(
                                            'seller.orders.show',
                                            order.id,
                                        )}
                                        className="text-xs text-gray-500 hover:text-black"
                                    >
                                        View
                                    </Link>

                                    {order.status === 'pending' && (
                                        <button
                                            onClick={() =>
                                                updateStatus(
                                                    order.id,
                                                    'confirmed',
                                                )
                                            }
                                            className="text-xs text-blue-600 hover:underline"
                                        >
                                            Confirm
                                        </button>
                                    )}

                                    {order.status === 'confirmed' && (
                                        <button
                                            onClick={() =>
                                                handleShipped(order.id)
                                            }
                                            className="text-xs text-purple-600 hover:underline"
                                        >
                                            Mark shipped
                                        </button>
                                    )}

                                    {order.status === 'pending' && (
                                        <button
                                            onClick={() =>
                                                updateStatus(
                                                    order.id,
                                                    'cancelled',
                                                )
                                            }
                                            className="text-xs text-red-500 hover:underline"
                                        >
                                            Cancel
                                        </button>
                                    )}
                                </div>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>

            {pageProps.data.length === 0 && (
                <p className="mt-8 text-center text-gray-400">No orders yet.</p>
            )}
        </div>
    );
};

Index.layout = (page: React.ReactNode) => <SellerLayout children={page} />;

export default Index;
