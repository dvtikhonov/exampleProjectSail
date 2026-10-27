package ru.sail.salesoutlets.api.auth;

import com.fasterxml.jackson.databind.ObjectMapper;
import jakarta.servlet.FilterChain;
import jakarta.servlet.ServletException;
import jakarta.servlet.http.HttpServletRequest;
import jakarta.servlet.http.HttpServletResponse;
import java.io.IOException;
import java.util.Map;
import java.util.Optional;
import org.springframework.http.MediaType;
import org.springframework.web.filter.OncePerRequestFilter;
import ru.sail.salesoutlets.domain.auth.GatewayUser;
import ru.sail.salesoutlets.domain.auth.GatewayUserResolver;

/**
 * Проверяет заголовок {@code X-User-Id} для {@code /api/*} (кроме health).
 * Аналог TrustGatewayAuth middleware в service-a / TrustGatewayAuthSubscriber в service-e.
 */
public class TrustGatewayAuthFilter extends OncePerRequestFilter {

    public static final String UNAUTHORIZED_MESSAGE = "Unauthorized gateway user.";

    private final GatewayUserResolver userResolver;
    private final ObjectMapper objectMapper;

    public TrustGatewayAuthFilter(GatewayUserResolver userResolver, ObjectMapper objectMapper) {
        this.userResolver = userResolver;
        this.objectMapper = objectMapper;
    }

    @Override
    protected boolean shouldNotFilter(HttpServletRequest request) {
        String path = request.getRequestURI();
        if (path == null) {
            return true;
        }
        if ("/api/health".equals(path)) {
            return true;
        }
        return !path.startsWith("/api/");
    }

    @Override
    protected void doFilterInternal(
            HttpServletRequest request,
            HttpServletResponse response,
            FilterChain filterChain
    ) throws ServletException, IOException {
        Optional<GatewayUser> user = resolveFromHeader(request.getHeader(GatewayAuth.USER_ID_HEADER));

        if (user.isEmpty()) {
            response.setStatus(HttpServletResponse.SC_UNAUTHORIZED);
            response.setContentType(MediaType.APPLICATION_JSON_VALUE);
            objectMapper.writeValue(response.getOutputStream(), Map.of("message", UNAUTHORIZED_MESSAGE));
            return;
        }

        request.setAttribute(GatewayAuth.USER_ATTRIBUTE, user.get());
        filterChain.doFilter(request, response);
    }

    private Optional<GatewayUser> resolveFromHeader(String headerValue) {
        if (headerValue == null || headerValue.isBlank()) {
            return Optional.empty();
        }
        String trimmed = headerValue.trim();
        if (!trimmed.chars().allMatch(Character::isDigit)) {
            return Optional.empty();
        }
        try {
            long userId = Long.parseLong(trimmed);
            if (userId <= 0) {
                return Optional.empty();
            }
            return userResolver.resolveById(userId);
        } catch (NumberFormatException ex) {
            return Optional.empty();
        }
    }
}
