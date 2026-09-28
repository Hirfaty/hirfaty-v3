import type { PageProps as InertiaPageProps } from '@inertiajs/core';
import type { Auth } from './auth';

export type * from './auth';
export type * from './navigation';
export type * from './ui';
export type * from './models';

export interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
    next_page_url: string | null;
    prev_page_url: string | null;
    links: {
        url: string | null;
        label: string;
        active: boolean;
    }[];
}

/**
 * A password reset link minted by an admin for another user, returned so it
 * can be copied and delivered by hand instead of emailed.
 */
export interface ResetLinkPayload {
    url: string;
    email: string;
    expires_in_minutes: number;
}

/**
 * The signed-in seller's shop, reduced to what the status bar needs — shaped
 * by HandleInertiaRequests::getSellerStoreStatus(). Null for anyone who is not
 * a seller with a shop.
 */
export interface SellerStoreStatus {
    store_name: string;
    /** For linking to the public shop page — `route('stores.show', slug)`. */
    slug: string;
    /** False once an admin has switched the shop off (Store::status). */
    is_public: boolean;
    verification_status: 'pending' | 'approved' | 'rejected';
    rejection_reason: string | null;
    /** Names from Store::REQUIRED_PROFILE_FIELDS that are still blank. */
    missing_fields: string[];
    /** Approved *and* complete — the gate on publishing products. */
    can_sell: boolean;
}

/**
 * One row of the admin's in-app notifications, as
 * AdminNotificationService::present() shapes it. Title and body are already
 * written out (in French, like the rest of /admin).
 */
export interface AdminNotification {
    id: string;
    /** e.g. 'purchase.placed' — picks the icon. */
    kind: string;
    title: string;
    body: string;
    /** Where opening it leads, once it has been marked read. */
    url: string;
    read_at: string | null;
    created_at: string | null;
}

/** The header bell's payload. Null for anyone who is not a super_admin. */
export interface AdminNotificationSummary {
    unread_count: number;
    latest: AdminNotification[];
}

export interface PageProps extends InertiaPageProps {
    auth: Auth;
    name: string;
    sidebarOpen: boolean;
    /** Set only on the staging host: where the real site lives. */
    staging?: { production_url: string } | null;
    sellerStore?: SellerStoreStatus | null;
    adminNotifications?: AdminNotificationSummary | null;
    flash: {
        success?: string;
        error?: string;
        /** Set only by the admin "generate reset link" action. */
        reset_link?: ResetLinkPayload;
    };
    locale: 'ar' | 'fr' | 'en';
    direction: 'ltr' | 'rtl';
    translations: Record<string, Record<string, string>>;
    cart: {
        items: Array<{
            id: number;
            name: string;
            seller: string;
            qty: number;
            price: number;
            line_total: number;
        }>;
        count: number;
        subtotal: number;
    };
    [key: string]: unknown;
}
