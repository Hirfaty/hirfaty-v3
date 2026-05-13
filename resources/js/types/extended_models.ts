import type { Category, Media, Product, Store } from './models';

export type BuyerProductResource = Omit<
    Product,
    | 'created_at'
    | 'updated_at'
    | 'status'
    | 'rejection_reason'
    | 'reviewed_by'
    | 'reviewed_at'
> & {
    images: Media[];
    videos: Media[];
    category: Category;
    store: Pick<Store, 'id' | 'store_name' | 'slug' | 'description'> & {
        city_name: string;
    };
};

// export interface BuyerCheckout {
//     cart: Cart;
// }
