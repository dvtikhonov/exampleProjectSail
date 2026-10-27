package ru.sail.salesoutlets.api.auth;

import jakarta.servlet.http.HttpServletRequest;
import ru.sail.salesoutlets.domain.auth.GatewayAuthSession;
import ru.sail.salesoutlets.domain.auth.GatewayUser;

/**
 * Сохраняет {@link GatewayUser} в атрибуты текущего HTTP-запроса.
 */
public class RequestGatewayAuthSession implements GatewayAuthSession {

    private final HttpServletRequest request;

    public RequestGatewayAuthSession(HttpServletRequest request) {
        this.request = request;
    }

    @Override
    public void login(GatewayUser user) {
        request.setAttribute(GatewayAuth.USER_ATTRIBUTE, user);
    }
}
