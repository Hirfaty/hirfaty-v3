import { router, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { useGuestCart } from '@/hooks/useGuestCart';

export default function CartSync() {
    const { auth } = usePage<any>().props;
    const { getItems, clearCart } = useGuestCart();

    useEffect(() => {
        const items = getItems();

        if (auth?.user?.role === 'buyer' && items.length > 0) {
            router.post(
                route('cart.merge'),
                {
                    items: items.map((item) => ({
                        product_id: item.product_id,
                        // Carried through so the variant the guest chose is
                        // the one that lands in their account's cart. The
                        // server drops lines whose variant has since gone
                        // away rather than failing the whole merge.
                        variant_id: item.variant_id ?? null,
                        quantity: item.quantity,
                    })),
                },
                {
                    onSuccess: () => {
                        clearCart();
                    },
                    preserveScroll: true,
                },
            );
        }
    }, [auth?.user]);

    return null;
}
