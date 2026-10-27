package ru.sail.salesoutlets.api.auth;

import jakarta.servlet.http.HttpServletRequest;
import ru.sail.salesoutlets.domain.auth.GatewayUser;
import ru.sail.salesoutlets.domain.auth.GatewayUserContext;

/**
 * Читает id gateway-пользователя из атрибутов текущего HTTP-запроса.
 */
public class RequestGatewayUserContext implements GatewayUserContext {

    private final HttpServletRequest request;

    public RequestGatewayUserContext(HttpServletRequest request) {
        this.request = request;
    }

    @Override
    public Long currentUserId() {
        Object attribute = request.getAttribute(GatewayAuth.USER_ATTRIBUTE);
        if (attribute instanceof GatewayUser user) {
            return user.id();
        }
        return null;
    }
}
