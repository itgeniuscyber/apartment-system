export type User = {
    id: number;
    name: string;
    email: string;
    role: 'super_admin' | 'administrator' | 'landlord' | 'manager' | 'tenant';
    is_active: boolean;
    permissions?: string[] | null;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
    access?: {
        is_management: boolean;
        is_super_admin: boolean;
        permissions: string[];
    };
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
