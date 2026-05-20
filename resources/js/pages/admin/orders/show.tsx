import AdminLayout from '@/layouts/admin/layout';
import type { Order, PageProps } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    CheckCircle2,
    Clock,
    MapPin,
    Package,
    Phone,
    ShoppingBag,
    Truck,
    User,
} from 'lucide-react';
import { ReactNode } from 'react';
import { toast } from 'sonner';

const Show = ({ order }: PageProps & { order: Order }) => {
    const handleStatusUpdate = (status: string) => {
        router.patch(
            route('admin.orders.status', order.id),
            { status },
            {
                onSuccess: () => toast.success(`Order marked as ${status}`),
            },
        );
    };

    const getStatusStep = () => {
        switch (order.status) {
            case 'pending':
                return 1;
            case 'confirmed':
                return 2;
            case 'shipped':
                return 3;
            case 'delivered':
                return 4;
            default:
                return 0;
        }
    };

    return (
        <div className="min-h-screen bg-cream/30 p-6 md:p-10">
            <Head title={`Order #${order.id}`} />

            <div className="mx-auto max-w-5xl">
                <header className="mb-10 flex items-center justify-between">
                    <div>
                        <Link
                            href={route('admin.orders.index')}
                            className="flex items-center gap-2 text-[10px] font-bold uppercase tracking-widest text-ink-soft hover:text-ink transition-colors"
                        >
                            <ArrowLeft className="h-3 w-3" />
                            Back to Registry
                        </Link>
                        <h1 className="h-editorial mt-4 text-4xl text-ink">
                            Order <span className="italic">#{order.id}</span>
                        </h1>
                    </div>

                    <div className="flex gap-2">
                        {order.status === 'pending' && (
                            <button
                                onClick={() => handleStatusUpdate('confirmed')}
                                className="bg-ink px-6 py-3 text-xs font-bold uppercase tracking-widest text-cream hover:bg-terracotta transition-colors"
                            >
                                Confirm Order
                            </button>
                        )}
                        {order.status === 'confirmed' && (
                            <button
                                onClick={() => handleStatusUpdate('shipped')}
                                className="bg-ink px-6 py-3 text-xs font-bold uppercase tracking-widest text-cream hover:bg-terracotta transition-colors"
                            >
                                Mark as Shipped
                            </button>
                        )}
                        {order.status === 'shipped' && (
                            <button
                                onClick={() => handleStatusUpdate('delivered')}
                                className="bg-ink px-6 py-3 text-xs font-bold uppercase tracking-widest text-cream hover:bg-terracotta transition-colors"
                            >
                                Complete Delivery
                            </button>
                        )}
                        {order.status !== 'cancelled' &&
                            order.status !== 'delivered' && (
                                <button
                                    onClick={() =>
                                        handleStatusUpdate('cancelled')
                                    }
                                    className="border border-ink/10 px-6 py-3 text-xs font-bold uppercase tracking-widest text-ink hover:bg-red-50 hover:text-red-600 transition-colors"
                                >
                                    Cancel
                                </button>
                            )}
                    </div>
                </header>

                <div className="grid grid-cols-12 gap-10">
                    <div className="col-span-12 lg:col-span-8 space-y-10">
                        {/* Status Progress */}
                        <section className="border border-ink/5 bg-white p-8 shadow-sm">
                            <div className="flex justify-between">
                                {[
                                    { id: 'pending', label: 'Received', icon: Clock },
                                    { id: 'confirmed', label: 'Confirmed', icon: CheckCircle2 },
                                    { id: 'shipped', label: 'In Transit', icon: Truck },
                                    { id: 'delivered', label: 'Delivered', icon: Package },
                                ].map((step, i) => {
                                    const isActive = getStatusStep() > i;
                                    const isCurrent = getStatusStep() === i + 1;
                                    return (
                                        <div key={step.id} className="flex flex-col items-center gap-3 relative flex-1">
                                            <div className={`z-10 flex h-10 w-10 items-center justify-center rounded-full border-2 transition-colors ${isActive || isCurrent ? 'border-terracotta bg-terracotta text-cream' : 'border-ink/5 bg-cream/20 text-ink/20'}`}>
                                                <step.icon className="h-4 w-4" />
                                            </div>
                                            <span className={`text-[10px] font-bold uppercase tracking-widest ${isCurrent ? 'text-ink' : 'text-ink-soft'}`}>
                                                {step.label}
                                            </span>
                                            {i < 3 && (
                                                <div className={`absolute top-5 left-1/2 w-full h-0.5 -z-0 ${getStatusStep() > i + 1 ? 'bg-terracotta' : 'bg-ink/5'}`} />
                                            )}
                                        </div>
                                    );
                                })}
                            </div>
                        </section>

                        {/* Items */}
                        <section className="border border-ink/5 bg-white shadow-sm overflow-hidden">
                            <div className="border-b border-ink/5 bg-ink/[0.02] px-8 py-5">
                                <h3 className="font-sans text-[10px] font-bold uppercase tracking-widest text-ink/40">
                                    Manifest Breakdown
                                </h3>
                            </div>
                            <div className="divide-y divide-ink/5">
                                {order.items?.map((item) => (
                                    <div key={item.id} className="flex items-center gap-6 p-8">
                                        <div className="h-20 w-20 bg-cream/50 overflow-hidden border border-ink/5">
                                            {item.product?.media?.[0] && (
                                                <img src={item.product.media[0].url} className="h-full w-full object-cover" />
                                            )}
                                        </div>
                                        <div className="flex-1">
                                            <p className="text-sm font-medium text-ink">{item.product?.name}</p>
                                            <p className="mt-1 text-[10px] text-ink-soft uppercase tracking-widest">
                                                Qty: {item.quantity} × {new Intl.NumberFormat('fr-MA', { style: 'currency', currency: 'MAD' }).format(Number(item.unit_price))}
                                            </p>
                                        </div>
                                        <div className="text-right">
                                            <p className="font-sans text-xs font-bold text-ink">
                                                {new Intl.NumberFormat('fr-MA', { style: 'currency', currency: 'MAD' }).format(Number(item.unit_price) * item.quantity)}
                                            </p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                            <div className="bg-cream/5 p-8 border-t border-ink/5">
                                <div className="flex justify-between items-center max-w-[300px] ml-auto">
                                    <span className="text-[10px] font-bold uppercase tracking-widest text-ink-soft">Order Total</span>
                                    <span className="h-editorial text-2xl text-ink">
                                        {new Intl.NumberFormat('fr-MA', { style: 'currency', currency: 'MAD' }).format(Number(order.total_amount))}
                                    </span>
                                </div>
                            </div>
                        </section>
                    </div>

                    <div className="col-span-12 lg:col-span-4 space-y-10">
                        {/* Buyer Info */}
                        <section className="border border-ink/5 bg-white p-8 shadow-sm">
                            <h3 className="font-sans text-[10px] font-bold uppercase tracking-widest text-ink/40 mb-6">
                                Client Acquisition
                            </h3>
                            <div className="space-y-6">
                                <div className="flex items-start gap-4">
                                    <div className="flex h-10 w-10 shrink-0 items-center justify-center bg-cream border border-ink/5 text-ink-soft">
                                        <User className="h-4 w-4" />
                                    </div>
                                    <div>
                                        <p className="text-sm font-medium text-ink">{order.buyer?.first_name} {order.buyer?.last_name}</p>
                                        <p className="text-[10px] text-ink-soft uppercase tracking-wider">{order.buyer?.email}</p>
                                    </div>
                                </div>
                                <div className="flex items-start gap-4">
                                    <div className="flex h-10 w-10 shrink-0 items-center justify-center bg-cream border border-ink/5 text-ink-soft">
                                        <Phone className="h-4 w-4" />
                                    </div>
                                    <div>
                                        <p className="text-[10px] font-bold uppercase tracking-widest text-ink/40 mb-0.5">Contact</p>
                                        <p className="text-sm font-medium text-ink">{order.buyer?.phone_number || 'N/A'}</p>
                                    </div>
                                </div>
                                <div className="flex items-start gap-4">
                                    <div className="flex h-10 w-10 shrink-0 items-center justify-center bg-cream border border-ink/5 text-ink-soft">
                                        <MapPin className="h-4 w-4" />
                                    </div>
                                    <div>
                                        <p className="text-[10px] font-bold uppercase tracking-widest text-ink/40 mb-0.5">Destination</p>
                                        <p className="text-sm font-medium text-ink">{order.city?.name}</p>
                                        <p className="mt-1 text-xs text-ink/60 leading-relaxed">{order.shipping_address}</p>
                                    </div>
                                </div>
                            </div>
                        </section>

                        {/* Artisan Info */}
                        <section className="border border-ink/5 bg-white p-8 shadow-sm">
                            <h3 className="font-sans text-[10px] font-bold uppercase tracking-widest text-ink/40 mb-6">
                                Fulfilling Artisan
                            </h3>
                            <div className="flex items-start gap-4">
                                <div className="flex h-10 w-10 shrink-0 items-center justify-center bg-cream border border-ink/5 text-ink-soft">
                                    <ShoppingBag className="h-4 w-4" />
                                </div>
                                <div>
                                    <p className="text-sm font-medium text-ink">{order.store?.store_name}</p>
                                    <Link href={route('admin.users.index')} className="text-[10px] font-bold uppercase tracking-widest text-terracotta hover:text-ink">
                                        Visit Storefront
                                    </Link>
                                </div>
                            </div>
                        </section>

                        {/* Financials */}
                        <section className="border border-ink/5 bg-white p-8 shadow-sm">
                            <h3 className="font-sans text-[10px] font-bold uppercase tracking-widest text-ink/40 mb-6">
                                Revenue Allocation
                            </h3>
                            <div className="space-y-4 text-xs">
                                <div className="flex justify-between">
                                    <span className="text-ink-soft">Platform Fee</span>
                                    <span className="font-medium text-ink">-{new Intl.NumberFormat('fr-MA', { style: 'currency', currency: 'MAD' }).format(Number(order.platform_fee))}</span>
                                </div>
                                <div className="flex justify-between border-t border-ink/5 pt-4">
                                    <span className="text-ink font-bold uppercase tracking-widest text-[10px]">Artisan Share</span>
                                    <span className="font-bold text-terracotta">{new Intl.NumberFormat('fr-MA', { style: 'currency', currency: 'MAD' }).format(Number(order.seller_earnings))}</span>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>
            </div>
        </div>
    );
};

Show.layout = (page: ReactNode) => <AdminLayout children={page} />;
export default Show;
