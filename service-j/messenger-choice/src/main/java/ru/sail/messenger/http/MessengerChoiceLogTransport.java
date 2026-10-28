package ru.sail.messenger.http;

import java.net.URI;
import java.util.Collection;
import java.util.HashSet;
import java.util.Locale;
import java.util.Objects;
import java.util.Set;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.http.MediaType;
import org.springframework.web.client.RestClient;

/**
 * POST JSON на http(s) хост из allow-list.
 * Не знает, визит это или клик: тело и URL задаёт вызывающий логгер.
 * Пустой URL или токен, чужой хост, схема не http/https и userinfo — вызов пропускается.
 * Токен в лог не пишется. Сбой сети и ответ не 2xx не пробрасываются.
 */
final class MessengerChoiceLogTransport {

    /** Общий секрет с service-c. Без него запись в max_log не делается. */
    static final String TOKEN_HEADER = "X-Messenger-Choice-Token";

    private static final Logger log = LoggerFactory.getLogger(MessengerChoiceLogTransport.class);

    private final RestClient restClient;
    private final String logToken;
    private final Set<String> allowedHosts;

    MessengerChoiceLogTransport(RestClient restClient, String logToken, Collection<String> allowedHosts) {
        this.restClient = Objects.requireNonNull(restClient, "restClient");
        this.logToken = logToken == null ? "" : logToken.trim();
        this.allowedHosts = normalizeHosts(Objects.requireNonNull(allowedHosts, "allowedHosts"));
    }

    void post(String url, Object body) {
        String targetUrl = url == null ? "" : url.trim();
        if (targetUrl.isEmpty() || logToken.isEmpty()) {
            log.warn("Messenger choice log is not configured");
            return;
        }
        URI target = permittedTarget(targetUrl);
        if (target == null) {
            return;
        }
        try {
            restClient.post()
                    .uri(target)
                    .header(TOKEN_HEADER, logToken)
                    .contentType(MediaType.APPLICATION_JSON)
                    .body(body)
                    .retrieve()
                    .toBodilessEntity();
        } catch (RuntimeException ex) {
            log.error("Messenger choice log failed: {}", ex.toString());
        }
    }

    /**
     * @return URI для POST или {@code null}, если запрос и токен отправлять нельзя
     */
    private URI permittedTarget(String url) {
        final URI uri;
        try {
            uri = URI.create(url);
        } catch (IllegalArgumentException ex) {
            rejectUntrusted(null, null);
            return null;
        }
        if (!isPermitted(uri)) {
            rejectUntrusted(uri.getScheme(), uri.getHost());
            return null;
        }
        return uri;
    }

    private boolean isPermitted(URI uri) {
        if (uri.getRawUserInfo() != null) {
            return false;
        }
        String scheme = uri.getScheme();
        if (!"http".equalsIgnoreCase(scheme) && !"https".equalsIgnoreCase(scheme)) {
            return false;
        }
        String host = uri.getHost();
        if (host == null || host.isEmpty()) {
            return false;
        }
        return allowedHosts.contains(host.toLowerCase(Locale.ROOT));
    }

    /**
     * В сообщение не входят сырой URL и токен: в userinfo может быть секрет.
     */
    private static void rejectUntrusted(String scheme, String host) {
        log.warn(
                "Messenger choice log skipped: scheme or host is not allowed (scheme={}, host={})",
                scheme == null ? "" : scheme,
                host == null ? "" : host
        );
    }

    private static Set<String> normalizeHosts(Collection<String> hosts) {
        Set<String> normalized = new HashSet<>();
        for (String host : hosts) {
            if (host == null) {
                continue;
            }
            String trimmed = host.trim().toLowerCase(Locale.ROOT);
            if (!trimmed.isEmpty()) {
                normalized.add(trimmed);
            }
        }
        return Set.copyOf(normalized);
    }
}
