/**
 * Пользователь API (Sanctum session).
 */
export interface AuthUser {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string | null;
    created_at?: string;
    updated_at?: string;
}

export interface LoginCredentials {
    email: string;
    password: string;
}

export interface RegisterPayload {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
}

export interface AuthUserResponse {
    user: AuthUser | null;
}

export interface ApiValidationError {
    message?: string;
    errors?: Record<string, string[]>;
}
