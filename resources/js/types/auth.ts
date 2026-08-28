export type User = {
    id: number;
    first_name: string;
    last_name: string;
    phone_number: string;
    email: string;
    is_banned: boolean;
    avatar?: string;
    email_verified_at: string | null;
    terms_accepted: boolean;
    terms_accepted_at: string | null;
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
