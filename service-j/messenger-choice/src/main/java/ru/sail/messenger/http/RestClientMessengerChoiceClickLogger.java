package ru.sail.messenger.http;

import com.fasterxml.jackson.annotation.JsonProperty;
import java.util.Collection;
import java.util.Objects;
import org.springframework.web.client.RestClient;
import ru.sail.messenger.port.MessengerChoiceClick;
import ru.sail.messenger.port.MessengerChoiceClickLogger;
import ru.sail.messenger.port.MessengerChoiceVisit;

/**
 * HTTP-адаптер порта клика.
 * Тело: {@code host}, {@code ip}, {@code user_agent}, {@code messenger} ({@code telegram} или {@code max}).
 * URL бота в тело не входит.
 */
public final class RestClientMessengerChoiceClickLogger implements MessengerChoiceClickLogger {

    private final MessengerChoiceLogTransport transport;
    private final String logUrl;

    public RestClientMessengerChoiceClickLogger(
            RestClient restClient,
            String logUrl,
            String logToken,
            Collection<String> allowedHosts
    ) {
        this.logUrl = logUrl == null ? "" : logUrl.trim();
        this.transport = new MessengerChoiceLogTransport(restClient, logToken, allowedHosts);
    }

    @Override
    public void logClick(MessengerChoiceClick click) {
        Objects.requireNonNull(click, "click");
        MessengerChoiceVisit visit = click.visit();
        transport.post(
                logUrl,
                new MessengerChoiceClickPayload(
                        visit.host(),
                        visit.ip(),
                        visit.userAgent(),
                        click.messenger().code()
                )
        );
    }

    private record MessengerChoiceClickPayload(
            String host,
            String ip,
            @JsonProperty("user_agent") String userAgent,
            String messenger
    ) {
    }
}
