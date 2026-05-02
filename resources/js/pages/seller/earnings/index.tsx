import { Earning } from '@/types';
import { PaginatedData } from '@/types';
import SellerLayout from '@/layouts/seller/layout';

type Summary = {
    total_earnings: number;
    available_earnings: number;
    pending_earnings: number;
};

const Index = ({
    summary,
    earnings,
}: {
    summary: Summary;
    earnings: PaginatedData<Earning>;
}) => {
    console.log('earnings: ', earnings);
    return (
        <div className="p-8">
            <h1 className="mb-6 text-2xl font-bold">Earnings</h1>

            {/* Summary cards */}
            <div className="mb-8 grid grid-cols-3 gap-4">
                <div className="border p-4">
                    <p className="text-xs text-gray-500 uppercase">
                        Total earned
                    </p>
                    <p className="mt-1 text-2xl font-bold">
                        {summary.total_earnings} MAD
                    </p>
                </div>
                <div className="border p-4">
                    <p className="text-xs text-gray-500 uppercase">Available</p>
                    <p className="mt-1 text-2xl font-bold text-green-600">
                        {summary.available_earnings} MAD
                    </p>
                </div>
                <div className="border p-4">
                    <p className="text-xs text-gray-500 uppercase">Pending</p>
                    <p className="mt-1 text-2xl font-bold text-yellow-600">
                        {summary.pending_earnings} MAD
                    </p>
                </div>
            </div>

            {/* Earnings table */}
            <table className="w-full border-collapse text-sm">
                <thead>
                    <tr className="border-b text-left text-xs text-gray-500 uppercase">
                        <th className="pr-4 pb-3">Order</th>
                        <th className="pr-4 pb-3">Buyer</th>
                        <th className="pr-4 pb-3">Gross</th>
                        <th className="pr-4 pb-3">Platform fee</th>
                        <th className="pr-4 pb-3">Amana fee</th>
                        <th className="pr-4 pb-3">Net</th>
                        <th className="pr-4 pb-3">Status</th>
                        <th className="pb-3">Date</th>
                    </tr>
                </thead>
                <tbody className="divide-y">
                    {earnings.data.map((earning) => (
                        <tr key={earning.id}>
                            <td className="py-3 pr-4">#{earning.id}</td>
                            <td className="py-3 pr-4">
                                {earning.order.buyer.first_name}{' '}
                                {earning.order.buyer.last_name}
                            </td>
                            <td className="py-3 pr-4">
                                {earning.gross_amount} MAD
                            </td>
                            <td className="py-3 pr-4 text-red-500">
                                -{earning.platform_fee} MAD
                            </td>
                            <td className="py-3 pr-4 text-red-500">
                                -{earning.amana_fee} MAD
                            </td>
                            <td className="py-3 pr-4 font-medium">
                                {earning.net_amount} MAD
                            </td>
                            <td className="py-3 pr-4">
                                <span
                                    className={`rounded px-2 py-1 text-xs ${
                                        earning.status === 'paid_out'
                                            ? 'bg-green-100 text-green-700'
                                            : earning.status === 'available'
                                              ? 'bg-blue-100 text-blue-700'
                                              : 'bg-yellow-100 text-yellow-700'
                                    }`}
                                >
                                    {earning.status}
                                </span>
                            </td>
                            <td className="py-3">
                                {new Date(
                                    earning.created_at,
                                ).toLocaleDateString()}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>

            {earnings.data.length === 0 && (
                <p className="mt-8 text-center text-gray-400">
                    No earnings yet.
                </p>
            )}
        </div>
    );
};

Index.layout = (page: React.ReactNode) => <SellerLayout>{page}</SellerLayout>;
export default Index;
