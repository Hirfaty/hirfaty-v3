import { Link, router, useForm } from '@inertiajs/react';
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

const Cart = ({ cart }: { cart: Cart }) => {
    // Group items by store
    const grouped = cart.items.reduce<
        Record<string, { storeName: string; items: CartItem[] }>
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

    const getPrice = (product: Product) =>
        parseFloat(product.sale_price ?? product.base_price);

    const itemTotal = (item: CartItem) =>
        getPrice(item.product) * item.quantity;

    const grandTotal = cart.items.reduce(
        (sum, item) => sum + itemTotal(item),
        0,
    );

    const removeItem = (itemId: number) => {
        router.delete(route('cart.remove', itemId));
    };

    const updateQuantity = (itemId: number, quantity: number) => {
        if (quantity < 1) return;
        router.patch(route('cart.update', itemId), { quantity });
    };

    if (cart.items.length === 0) {
        return (
            <div className="flex min-h-screen flex-col items-center justify-center gap-4">
                <p className="text-gray-500">Your cart is empty.</p>
                <Link
                    href={route('products.index')}
                    className="text-sm text-blue-600 hover:underline"
                >
                    Browse products →
                </Link>
            </div>
        );
    }

    return (
        <div className="mx-auto max-w-3xl p-8">
            <h1 className="mb-6 text-2xl font-bold">Your Cart</h1>

            <div className="space-y-8">
                {Object.entries(grouped).map(
                    ([storeId, { storeName, items }]) => {
                        const storeTotal = items.reduce(
                            (sum, item) => sum + itemTotal(item),
                            0,
                        );

                        return (
                            <div key={storeId} className="border">
                                {/* Store header */}
                                <div className="border-b bg-gray-50 px-4 py-3">
                                    <p className="text-sm font-semibold">
                                        {storeName}
                                    </p>
                                </div>

                                {/* Items */}
                                <div className="divide-y">
                                    {items.map((item) => (
                                        <div
                                            key={item.id}
                                            className="flex items-center gap-4 p-4"
                                        >
                                            {/* Image */}
                                            {item.product.images[0] ? (
                                                <img
                                                    src={
                                                        item.product.images[0]
                                                            .url
                                                    }
                                                    alt={item.product.name}
                                                    className="h-16 w-16 object-cover"
                                                />
                                            ) : (
                                                <div className="h-16 w-16 bg-gray-100" />
                                            )}

                                            {/* Name + price */}
                                            <div className="flex-1">
                                                <p className="text-sm font-medium">
                                                    {item.product.name}
                                                </p>
                                                <p className="text-sm text-gray-500">
                                                    {getPrice(
                                                        item.product,
                                                    ).toFixed(2)}{' '}
                                                    MAD
                                                    {item.product
                                                        .sale_price && (
                                                        <span className="ml-2 text-gray-400 line-through">
                                                            {
                                                                item.product
                                                                    .base_price
                                                            }{' '}
                                                            MAD
                                                        </span>
                                                    )}
                                                </p>
                                            </div>

                                            {/* Quantity */}
                                            <div className="flex items-center gap-2">
                                                <button
                                                    onClick={() =>
                                                        updateQuantity(
                                                            item.id,
                                                            item.quantity - 1,
                                                        )
                                                    }
                                                    className="h-7 w-7 border text-sm hover:bg-gray-100"
                                                >
                                                    −
                                                </button>
                                                <span className="w-6 text-center text-sm">
                                                    {item.quantity}
                                                </span>
                                                <button
                                                    onClick={() =>
                                                        updateQuantity(
                                                            item.id,
                                                            item.quantity + 1,
                                                        )
                                                    }
                                                    className="h-7 w-7 border text-sm hover:bg-gray-100"
                                                >
                                                    +
                                                </button>
                                            </div>

                                            {/* Subtotal */}
                                            <p className="w-24 text-right text-sm font-medium">
                                                {itemTotal(item).toFixed(2)} MAD
                                            </p>

                                            {/* Remove */}
                                            <button
                                                onClick={() =>
                                                    removeItem(item.id)
                                                }
                                                className="text-xs text-red-400 hover:text-red-600"
                                            >
                                                Remove
                                            </button>
                                        </div>
                                    ))}
                                </div>

                                {/* Store subtotal */}
                                <div className="flex justify-end border-t px-4 py-3">
                                    <p className="text-sm text-gray-500">
                                        Store subtotal:{' '}
                                        <span className="font-medium text-black">
                                            {storeTotal.toFixed(2)} MAD
                                        </span>
                                    </p>
                                </div>
                            </div>
                        );
                    },
                )}
            </div>

            {/* Grand total + checkout */}
            <div className="mt-8 border-t pt-6">
                <div className="flex items-center justify-between">
                    <div>
                        <p className="text-xs text-gray-400">
                            * Shipping (15 MAD) and platform fees applied at
                            checkout per store.
                        </p>
                    </div>
                    <div className="text-right">
                        <p className="text-sm text-gray-500">
                            Total:{' '}
                            <span className="text-xl font-bold text-black">
                                {grandTotal.toFixed(2)} MAD
                            </span>
                        </p>
                        <Link
                            href={route('checkout.show')}
                            className="mt-3 inline-block bg-black px-8 py-3 text-sm text-white hover:bg-gray-800"
                        >
                            Proceed to checkout →
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default Cart;
