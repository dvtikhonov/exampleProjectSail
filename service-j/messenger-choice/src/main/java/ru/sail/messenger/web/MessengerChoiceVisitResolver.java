package ru.sail.messenger.web;

import jakarta.servlet.http.HttpServletRequest;
import ru.sail.messenger.port.MessengerChoiceVisit;

/**
 * Собирает визит из заголовков прокси: хост, адрес клиента и User-Agent.
 */
public final class MessengerChoiceVisitResolver {

    /** Как {@code Str::limit} в логе mini-app: дольше этого service-c всё равно не хранит. */
    static final int USER_AGENT_MAX_LENGTH = 120;

    private static final int HOST_MAX_LENGTH = 255;
    private static final int IP_MAX_LENGTH = 45;

    /**
     * Читает {@code X-Forwarded-Host} / {@code Host}, {@code X-Real-IP} / {@code X-Forwarded-For}
     * и {@code User-Agent}.
     */
    public MessengerChoiceVisit resolve(HttpServletRequest request) {
        String host = hostWithoutPort(firstListValue(request.getHeader("X-Forwarded-Host")));
        if (host.isEmpty()) {
            host = hostWithoutPort(firstListValue(request.getHeader("Host")));
        }
        String ip = firstListValue(request.getHeader("X-Real-IP"));
        if (ip.isEmpty()) {
            ip = firstListValue(request.getHeader("X-Forwarded-For"));
        }
        String userAgent = request.getHeader("User-Agent");
        return new MessengerChoiceVisit(
                limit(host, HOST_MAX_LENGTH),
                ip.isEmpty() ? null : limit(ip, IP_MAX_LENGTH),
                limit(userAgent == null ? "" : userAgent.trim(), USER_AGENT_MAX_LENGTH)
        );
    }

    /**
     * Первое непустое значение из списка через запятую.
     */
    private static String firstListValue(String header) {
        if (header == null || header.isBlank()) {
            return "";
        }
        for (String part : header.split(",")) {
            String trimmed = part.trim();
            if (!trimmed.isEmpty()) {
                return trimmed;
            }
        }
        return "";
    }

    /**
     * Убирает порт. Для {@code [IPv6]:port} возвращает адрес без скобок.
     */
    private static String hostWithoutPort(String host) {
        if (host.isEmpty()) {
            return "";
        }
        if (host.startsWith("[")) {
            int end = host.indexOf(']');
            if (end > 1) {
                return host.substring(1, end);
            }
        }
        int colon = host.indexOf(':');
        if (colon < 0) {
            return host;
        }
        return host.substring(0, colon);
    }

    private static String limit(String value, int maxLength) {
        if (value.length() <= maxLength) {
            return value;
        }
        return value.substring(0, maxLength);
    }
}
