export type UserRole =
    | 'super_admin'
    | 'seller'
    | 'buyer'
    | 'professional_buyer';

export type User = {
    id: number;
    first_name: string;
    last_name: string;
    phone_number: string | null;
    /**
     * Null on an account an admin created before having the artisan's
     * details. Such an account cannot log in until an address is filled in.
     */
    email: string | null;
    role: UserRole;
    is_banned: boolean;
    avatar?: string;
    /** Free-text description: self-written by sellers, admin-written for buyers. */
    bio?: string | null;
    email_verified_at: string | null;
    /** Set once the buyer has been through (or skipped) the intro tour. */
    onboarded_at?: string | null;
    /** Only meaningful when role is 'professional_buyer'; null = not reviewed. */
    professional_verified_at?: string | null;
    /*
     * The buyer-initiated trade request. Separate from `role`: a buyer stays a
     * `buyer` while 'pending', and only an admin's approval promotes them to
     * professional_buyer. null = never asked.
     */
    professional_request_status?: 'pending' | 'approved' | 'declined' | null;
    professional_request_message?: string | null;
    professional_requested_at?: string | null;
    professional_decline_reason?: string | null;
    two_factor_enabled?: boolean;
    /** 'fr' | 'ar' | 'en'; drives which language their emails go out in. */
    locale?: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
