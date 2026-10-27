package ru.sail.salesoutlets.api.auth;

/**
 * Константы gateway-аутентификации (заголовок и атрибут запроса).
 */
public final class GatewayAuth {

    public static final String USER_ID_HEADER = "X-User-Id";
    public static final String USER_ATTRIBUTE = "gateway.user";

    private GatewayAuth() {
    }
}
