package ru.sail.messenger.http;

import com.fasterxml.jackson.annotation.JsonProperty;
import java.net.URI;
import java.util.Objects;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.http.MediaType;
import org.springframework.web.client.RestClient;
import ru.sail.messenger.port.MessengerChoiceVisit;
import ru.sail.messenger.port.MessengerChoiceVisitLogger;

/**
 * HTTP-адаптер порта визита: {@code POST} в service-c.
 * Тело: {@code host}, {@code ip}, {@code user_agent}. Заголовок токена — {@link #TOKEN_HEADER}.
 * Сбой сети и ответ не 2xx не пробрасываются: страница выбора всё равно открывается.
 */
public final class RestClientMessengerChoiceVisitLogger implements MessengerChoiceVisitLogger {

    /** Общий секрет с service-c. Без него запись в max_log не делается. */
    public static final String TOKEN_HEADER = "X-Messenger-Choice-Token";

    private static final Logger log = LoggerFactory.getLogger(RestClientMessengerChoiceVisitLogger.class);

    private final RestClient restClient;
    private final String logUrl;
    private final String logToken;

    public RestClientMessengerChoiceVisitLogger(RestClient restClient, String logUrl, String logToken) {
        this.restClient = Objects.requireNonNull(restClient, "restClient");
        this.logUrl = logUrl == null ? "" : logUrl.trim();
        this.logToken = logToken == null ? "" : logToken.trim();
    }

    @Override
    public void logVisit(MessengerChoiceVisit visit) {
        Objects.requireNonNull(visit, "visit");
        if (logUrl.isEmpty() || logToken.isEmpty()) {
            log.warn("Messenger choice visit log is not configured");
            return;
        }
        try {
            restClient.post()
                    .uri(URI.create(logUrl))
                    .header(TOKEN_HEADER, logToken)
                    .contentType(MediaType.APPLICATION_JSON)
                    .body(new MessengerChoiceLogPayload(visit.host(), visit.ip(), visit.userAgent()))
                    .retrieve()
                    .toBodilessEntity();
        } catch (RuntimeException ex) {
            log.error("Messenger choice visit log failed: {}", ex.toString());
        }
    }

    private record MessengerChoiceLogPayload(
            String host,
            String ip,
            @JsonProperty("user_agent") String userAgent
    ) {
    }
}
