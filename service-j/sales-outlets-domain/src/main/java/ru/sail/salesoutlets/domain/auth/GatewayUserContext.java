package ru.sail.salesoutlets.domain.auth;

/**
 * Доступ к id текущего gateway-пользователя в рамках запроса.
 */
public interface GatewayUserContext {

    /** Возвращает id gateway-пользователя или {@code null}, если сессия не установлена. */
    Long currentUserId();
}
