export type UserRole =
    | 'super_admin'
    | 'seller'
    | 'buyer'
    | 'professional_buyer';

export type User = {
    id: number;
    first_name: string;
    last_name: string;
    phone_number: string;
    email: string;
    role: UserRole;
    is_banned: boolean;
    avatar?: string;
    /** Free-text description: self-written by sellers, admin-written for buyers. */
    bio?: string | null;
    email_verified_at: string | null;
    /** Only meaningful when role is 'professional_buyer'; null = not reviewed. */
    professional_verified_at?: string | null;
    two_factor_enabled?: boolean;
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
