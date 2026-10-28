package ru.sail.messenger.http;

import static org.junit.jupiter.api.Assertions.assertDoesNotThrow;
import static org.junit.jupiter.api.Assertions.assertFalse;
import static org.springframework.test.web.client.match.MockRestRequestMatchers.content;
import static org.springframework.test.web.client.match.MockRestRequestMatchers.header;
import static org.springframework.test.web.client.match.MockRestRequestMatchers.jsonPath;
import static org.springframework.test.web.client.match.MockRestRequestMatchers.method;
import static org.springframework.test.web.client.match.MockRestRequestMatchers.requestTo;
import static org.springframework.test.web.client.response.MockRestResponseCreators.withSuccess;

import ch.qos.logback.classic.Level;
import ch.qos.logback.classic.Logger;
import ch.qos.logback.classic.spi.ILoggingEvent;
import ch.qos.logback.core.read.ListAppender;
import java.util.List;
import org.junit.jupiter.api.Test;
import org.slf4j.LoggerFactory;
import org.springframework.http.HttpMethod;
import org.springframework.http.MediaType;
import org.springframework.test.web.client.MockRestServiceServer;
import org.springframework.web.client.RestClient;
import ru.sail.messenger.port.ChosenMessenger;
import ru.sail.messenger.port.MessengerChoiceClick;
import ru.sail.messenger.port.MessengerChoiceVisit;

class RestClientMessengerChoiceClickLoggerTest {

    private static final String LOG_URL = "http://service-c:8000/internal/messenger-choice/clicks";

    @Test
    void itPostsMessengerCodeWithoutBotUrl() {
        RestClient.Builder builder = RestClient.builder();
        MockRestServiceServer server = MockRestServiceServer.bindTo(builder).build();
        server.expect(requestTo(LOG_URL))
                .andExpect(method(HttpMethod.POST))
                .andExpect(header(MessengerChoiceLogTransport.TOKEN_HEADER, "secret"))
                .andExpect(content().contentType(MediaType.APPLICATION_JSON))
                .andExpect(jsonPath("$.host").value("example.test"))
                .andExpect(jsonPath("$.ip").value("203.0.113.8"))
                .andExpect(jsonPath("$.user_agent").value("agent"))
                .andExpect(jsonPath("$.messenger").value("telegram"))
                .andExpect(jsonPath("$.url").doesNotExist())
                .andRespond(withSuccess());

        logger(builder, LOG_URL, "secret").logClick(new MessengerChoiceClick(
                new MessengerChoiceVisit("example.test", "203.0.113.8", "agent"),
                ChosenMessenger.TELEGRAM
        ));

        server.verify();
    }

    @Test
    void itPostsMaxCode() {
        RestClient.Builder builder = RestClient.builder();
        MockRestServiceServer server = MockRestServiceServer.bindTo(builder).build();
        server.expect(requestTo(LOG_URL))
                .andExpect(jsonPath("$.messenger").value("max"))
                .andRespond(withSuccess());

        logger(builder, LOG_URL, "secret").logClick(new MessengerChoiceClick(
                new MessengerChoiceVisit("example.test", null, ""),
                ChosenMessenger.MAX
        ));

        server.verify();
    }

    @Test
    void itDoesNotCallWhenHostIsNotAllowed() {
        RestClient.Builder builder = RestClient.builder();
        MockRestServiceServer server = MockRestServiceServer.bindTo(builder).build();
        Logger logback = (Logger) LoggerFactory.getLogger(MessengerChoiceLogTransport.class);
        ListAppender<ILoggingEvent> logs = new ListAppender<>();
        logs.start();
        Level previous = logback.getLevel();
        logback.setLevel(Level.DEBUG);
        logback.addAppender(logs);
        try {
            assertDoesNotThrow(() -> logger(builder, "http://evil.example/clicks", "super-token").logClick(
                    new MessengerChoiceClick(
                            new MessengerChoiceVisit("example.test", "203.0.113.8", "agent"),
                            ChosenMessenger.MAX
                    )
            ));
            server.verify();
            for (ILoggingEvent event : logs.list) {
                assertFalse(event.getFormattedMessage().contains("super-token"), event.getFormattedMessage());
                assertFalse(event.getFormattedMessage().contains("id421816864057_bot"), event.getFormattedMessage());
            }
        } finally {
            logback.detachAppender(logs);
            logback.setLevel(previous);
        }
    }

    private static RestClientMessengerChoiceClickLogger logger(RestClient.Builder builder, String url, String token) {
        return new RestClientMessengerChoiceClickLogger(builder.build(), url, token, List.of("service-c"));
    }
}
