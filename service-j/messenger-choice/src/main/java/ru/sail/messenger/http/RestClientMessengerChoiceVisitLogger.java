package ru.sail.messenger.http;

import com.fasterxml.jackson.annotation.JsonProperty;
import java.util.Collection;
import java.util.Objects;
import org.springframework.web.client.RestClient;
import ru.sail.messenger.port.MessengerChoiceVisit;
import ru.sail.messenger.port.MessengerChoiceVisitLogger;

/**
 * HTTP-адаптер порта визита.
 * Тело: {@code host}, {@code ip}, {@code user_agent}. Куда слать и можно ли — решает транспорт.
 * Этот класс не знает адресов ботов и не пишет выбранный мессенджер.
 */
public final class RestClientMessengerChoiceVisitLogger implements MessengerChoiceVisitLogger {

    /** Общий секрет с service-c. Без него запись в max_log не делается. */
    public static final String TOKEN_HEADER = MessengerChoiceLogTransport.TOKEN_HEADER;

    private final MessengerChoiceLogTransport transport;
    private final String logUrl;

    public RestClientMessengerChoiceVisitLogger(
            RestClient restClient,
            String logUrl,
            String logToken,
            Collection<String> allowedHosts
    ) {
        this.logUrl = logUrl == null ? "" : logUrl.trim();
        this.transport = new MessengerChoiceLogTransport(restClient, logToken, allowedHosts);
    }

    @Override
    public void logVisit(MessengerChoiceVisit visit) {
        Objects.requireNonNull(visit, "visit");
        transport.post(logUrl, new MessengerChoiceLogPayload(visit.host(), visit.ip(), visit.userAgent()));
    }

    private record MessengerChoiceLogPayload(
            String host,
            String ip,
            @JsonProperty("user_agent") String userAgent
    ) {
    }
}
