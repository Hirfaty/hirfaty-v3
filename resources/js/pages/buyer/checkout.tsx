import { useForm } from '@inertiajs/react';
import React from 'react';

type Media = { id: number; url: string };

type Product = {
    id: number;
    name: string;
    base_price: string;
    sale_price: string | null;
    images: Media[];
    store: { id: number; store_name: string };
};

type CartItem = {
    id: number;
    quantity: number;
    product: Product;
};

type Cart = {
    id: number;
    items: CartItem[];
};

type City = { id: number; name: string };

const PLATFORM_FEE_PERCENT = 0.05;
const AMANA_FEE = 15;

const Checkout = ({ cart, cities }: { cart: Cart; cities: City[] }) => {
    const { data, setData, post, processing, errors } = useForm({
        shipping_address: '',
        city_id: cities[0]?.id ?? null,
        notes: '',
    });

    const getPrice = (product: Product) =>
        parseFloat(product.sale_price ?? product.base_price);

    // Group by store
    const grouped = cart.items.reduce<
        Record<number, { storeName: string; items: CartItem[] }>
    >((acc, item) => {
        const storeId = item.product.store.id;
        if (!acc[storeId]) {
            acc[storeId] = {
                storeName: item.product.store.store_name,
                items: [],
            };
        }
        acc[storeId].items.push(item);
        return acc;
    }, {});

    const storeCount = Object.keys(grouped).length;
    const productsTotal = cart.items.reduce(
        (sum, item) => sum + getPrice(item.product) * item.quantity,
        0,
    );
    const totalPlatformFee = parseFloat(
        (productsTotal * PLATFORM_FEE_PERCENT).toFixed(2),
    );
    const totalAmanaFee = AMANA_FEE * storeCount;
    const grandTotal = productsTotal + totalAmanaFee;

    const handleSubmit = () => {
        post(route('checkout.store'));
    };

    return (
        <div className="mx-auto grid max-w-5xl grid-cols-12 gap-8 p-8">
            <h1 className="col-span-12 text-2xl font-bold">Checkout</h1>

            {/* ── Left — form ── */}
            <div className="col-span-12 space-y-6 md:col-span-7">
                {/* Shipping address */}
                <div>
                    <label className="mb-1 block text-sm font-medium">
                        Shipping address
                    </label>
                    <textarea
                        value={data.shipping_address}
                        onChange={(e) =>
                            setData('shipping_address', e.target.value)
                        }
                        rows={3}
                        placeholder="Full address..."
                        className="w-full border p-3 text-sm focus:ring-1 focus:ring-black focus:outline-none"
                    />
                    {errors.shipping_address && (
                        <p className="mt-1 text-xs text-red-500">
                            {errors.shipping_address}
                        </p>
                    )}
                </div>

                {/* City */}
                <div>
                    <label className="mb-1 block text-sm font-medium">
                        City
                    </label>
                    <select
                        value={data.city_id ?? ''}
                        onChange={(e) =>
                            setData('city_id', Number(e.target.value))
                        }
                        className="w-full border p-3 text-sm focus:ring-1 focus:ring-black focus:outline-none"
                    >
                        {cities.map((city) => (
                            <option key={city.id} value={city.id}>
                                {city.name}
                            </option>
                        ))}
                    </select>
                    {errors.city_id && (
                        <p className="mt-1 text-xs text-red-500">
                            {errors.city_id}
                        </p>
                    )}
                </div>

                {/* Notes */}
                <div>
                    <label className="mb-1 block text-sm font-medium">
                        Notes{' '}
                        <span className="font-normal text-gray-400">
                            (optional)
                        </span>
                    </label>
                    <textarea
                        value={data.notes}
                        onChange={(e) => setData('notes', e.target.value)}
                        rows={2}
                        placeholder="Any notes for the sellers..."
                        className="w-full border p-3 text-sm focus:ring-1 focus:ring-black focus:outline-none"
                    />
                </div>

                {/* Orders breakdown per store */}
                <div>
                    <p className="mb-3 text-sm font-medium">
                        Your cart will generate {storeCount} order
                        {storeCount > 1 ? 's' : ''}:
                    </p>
                    <div className="space-y-3">
                        {Object.entries(grouped).map(
                            ([storeId, { storeName, items }]) => {
                                const storeSubtotal = items.reduce(
                                    (sum, item) =>
                                        sum +
                                        getPrice(item.product) * item.quantity,
                                    0,
                                );
                                const storePlatformFee = parseFloat(
                                    (
                                        storeSubtotal * PLATFORM_FEE_PERCENT
                                    ).toFixed(2),
                                );
                                const sellerEarnings =
                                    storeSubtotal -
                                    storePlatformFee -
                                    AMANA_FEE;

                                return (
                                    <div
                                        key={storeId}
                                        className="border p-4 text-sm"
                                    >
                                        <p className="mb-2 font-medium">
                                            {storeName}
                                        </p>
                                        <ul className="mb-3 space-y-1 text-gray-500">
                                            {items.map((item) => (
                                                <li
                                                    key={item.id}
                                                    className="flex justify-between"
                                                >
                                                    <span>
                                                        {item.product.name} ×{' '}
                                                        {item.quantity}
                                                    </span>
                                                    <span>
                                                        {(
                                                            getPrice(
                                                                item.product,
                                                            ) * item.quantity
                                                        ).toFixed(2)}{' '}
                                                        MAD
                                                    </span>
                                                </li>
                                            ))}
                                        </ul>
                                        <div className="space-y-1 border-t pt-2 text-xs text-gray-400">
                                            <div className="flex justify-between">
                                                <span>Amana shipping</span>
                                                <span>
                                                    {AMANA_FEE.toFixed(2)} MAD
                                                </span>
                                            </div>
                                            <div className="flex justify-between">
                                                <span>Platform fee (5%)</span>
                                                <span>
                                                    {storePlatformFee.toFixed(
                                                        2,
                                                    )}{' '}
                                                    MAD
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                );
                            },
                        )}
                    </div>
                </div>
            </div>

            {/* ── Right — summary ── */}
            <aside className="col-span-12 md:col-span-5">
                <div className="sticky top-8 border p-6">
                    <p className="mb-4 text-sm font-semibold tracking-wider uppercase">
                        Order summary
                    </p>

                    <div className="space-y-2 text-sm">
                        <div className="flex justify-between text-gray-500">
                            <span>Products ({cart.items.length})</span>
                            <span>{productsTotal.toFixed(2)} MAD</span>
                        </div>
                        <div className="flex justify-between text-gray-500">
                            <span>
                                Amana shipping ({storeCount} store
                                {storeCount > 1 ? 's' : ''})
                            </span>
                            <span>{totalAmanaFee.toFixed(2)} MAD</span>
                        </div>
                        <div className="flex justify-between text-gray-500">
                            <span>Platform fee (5%)</span>
                            <span>{totalPlatformFee.toFixed(2)} MAD</span>
                        </div>
                    </div>

                    <div className="mt-4 flex justify-between border-t pt-4 font-bold">
                        <span>Total</span>
                        <span>{grandTotal.toFixed(2)} MAD</span>
                    </div>

                    <p className="mt-3 text-xs text-gray-400">
                        Payment will be collected on delivery (Cash on Delivery
                        via Amana).
                    </p>

                    <button
                        onClick={handleSubmit}
                        disabled={processing}
                        className="mt-6 w-full bg-black py-3 text-sm text-white transition-colors hover:bg-gray-800 disabled:opacity-50"
                    >
                        {processing
                            ? 'Placing orders...'
                            : `Place ${storeCount} order${storeCount > 1 ? 's' : ''}`}
                    </button>
                </div>
            </aside>
        </div>
    );
};

export default Checkout;
