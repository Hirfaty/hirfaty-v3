import { User } from './auth';

export interface Store {
    id: number;
    user_id: number;
    category_id: number | null;
    city_id: number | null;
    store_name: string;
    slug: string;
    description: string | null;
    logo: string | null;
    banner: string | null;
    created_at: string;
    updated_at: string;
}

export interface Category {
    id: number;
    name: string;
    slug: string;
    parent_id: number | null;
    icon: string | null;
}

export interface City {
    id: number;
    name: string;
    region: string | null;
}

export interface Media {
    id: number;
    mediable_id: number;
    mediable_type: string;
    type: 'image' | 'video';
    path: string;
    url: string;
    disk: string;
    mime_type: string | null;
    size: number | null;
    order: number;
    created_at: string;
    updated_at: string;
}

export interface Product {
    id: number;
    store_id: number;
    category_id: number | null;
    name: string;
    slug: string;
    description: string | null;
    base_price: string;
    sale_price: string | null;
    stock: number;
    status: 'draft' | 'published' | 'archived';
    average_rating: string;
    category: Category | null;
    images: Media[];
    videos: Media[];
    created_at: string;
    updated_at: string;
}

export interface OrderItem {
    id: number;
    order_id: number;
    product_id: number;
    quantity: number;
    unit_price: string;
    subtotal: string;
    product: Product;
}

export interface Order {
    id: number;
    buyer_id: number;
    store_id: number;
    city_id: number | null;
    tracking_number: string | null;
    status: 'pending' | 'confirmed' | 'shipped' | 'delivered' | 'cancelled';
    total_amount: string;
    platform_fee: string;
    amana_fee: string;
    seller_earnings: string;
    shipping_address: string;
    notes: string | null;
    delivered_at: string | null;
    buyer: User;
    items: OrderItem[];
    city: City | null;
    created_at: string;
    updated_at: string;
}

export interface Earning {
    id: number;
    store_id: number;
    order_id: number;
    gross_amount: string;
    platform_fee: string;
    amana_fee: string;
    net_amount: string;
    status: 'pending' | 'available' | 'paid_out';
    created_at: string;
    updated_at: string;
    order: Order;
    store: Store;
    buyer: User;
}
