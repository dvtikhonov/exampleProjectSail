package ru.sail.salesoutlets.domain.auth;

/**
 * Сохраняет аутентифицированного gateway-пользователя в контексте запроса.
 */
public interface GatewayAuthSession {

    /** Сохраняет аутентифицированного пользователя в контексте текущего запроса. */
    void login(GatewayUser user);
}
