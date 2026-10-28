package ru.sail.messenger.http;

import static org.hamcrest.Matchers.equalToIgnoringCase;
import static org.junit.jupiter.api.Assertions.assertDoesNotThrow;
import static org.junit.jupiter.api.Assertions.assertFalse;
import static org.springframework.test.web.client.match.MockRestRequestMatchers.content;
import static org.springframework.test.web.client.match.MockRestRequestMatchers.header;
import static org.springframework.test.web.client.match.MockRestRequestMatchers.jsonPath;
import static org.springframework.test.web.client.match.MockRestRequestMatchers.method;
import static org.springframework.test.web.client.match.MockRestRequestMatchers.requestTo;
import static org.springframework.test.web.client.response.MockRestResponseCreators.withStatus;
import static org.springframework.test.web.client.response.MockRestResponseCreators.withSuccess;

import ch.qos.logback.classic.Level;
import ch.qos.logback.classic.Logger;
import ch.qos.logback.classic.spi.ILoggingEvent;
import ch.qos.logback.core.read.ListAppender;
import java.io.IOException;
import java.util.List;
import org.junit.jupiter.api.Test;
import org.slf4j.LoggerFactory;
import org.springframework.http.HttpMethod;
import org.springframework.http.HttpStatus;
import org.springframework.http.MediaType;
import org.springframework.test.web.client.MockRestServiceServer;
import org.springframework.test.web.client.response.MockRestResponseCreators;
import org.springframework.web.client.RestClient;
import ru.sail.messenger.port.MessengerChoiceVisit;

class RestClientMessengerChoiceVisitLoggerTest {

    private static final String LOG_URL = "http://service-c:8000/internal/messenger-choice";

    @Test
    void itPostsVisitWithTokenHeader() {
        RestClient.Builder builder = RestClient.builder();
        MockRestServiceServer server = MockRestServiceServer.bindTo(builder).build();
        server.expect(requestTo(LOG_URL))
                .andExpect(method(HttpMethod.POST))
                .andExpect(header(RestClientMessengerChoiceVisitLogger.TOKEN_HEADER, "secret"))
                .andExpect(content().contentType(MediaType.APPLICATION_JSON))
                .andExpect(jsonPath("$.host").value("example.test"))
                .andExpect(jsonPath("$.ip").value("203.0.113.8"))
                .andExpect(jsonPath("$.user_agent").value("agent"))
                .andRespond(withSuccess());

        logger(builder, LOG_URL, "secret").logVisit(new MessengerChoiceVisit("example.test", "203.0.113.8", "agent"));

        server.verify();
    }

    @Test
    void itDoesNotThrowWhenServiceIsUnavailable() {
        RestClient.Builder builder = RestClient.builder();
        MockRestServiceServer server = MockRestServiceServer.bindTo(builder).build();
        server.expect(requestTo(LOG_URL))
                .andRespond(withStatus(HttpStatus.SERVICE_UNAVAILABLE));

        assertDoesNotThrow(() -> logger(builder, LOG_URL, "secret")
                .logVisit(new MessengerChoiceVisit("example.test", null, "agent")));

        server.verify();
    }

    @Test
    void itDoesNotThrowWhenConnectionFails() {
        RestClient.Builder builder = RestClient.builder();
        MockRestServiceServer server = MockRestServiceServer.bindTo(builder).build();
        server.expect(requestTo(LOG_URL))
                .andRespond(MockRestResponseCreators.withException(new IOException("connection refused")));

        assertDoesNotThrow(() -> logger(builder, LOG_URL, "secret")
                .logVisit(new MessengerChoiceVisit("example.test", "203.0.113.8", "agent")));

        server.verify();
    }

    @Test
    void itSkipsCallWhenUrlOrTokenMissing() {
        RestClient.Builder builder = RestClient.builder();
        MockRestServiceServer server = MockRestServiceServer.bindTo(builder).build();

        assertDoesNotThrow(() -> logger(builder, "", "secret")
                .logVisit(new MessengerChoiceVisit("example.test", "203.0.113.8", "agent")));
        assertDoesNotThrow(() -> logger(builder, LOG_URL, "  ")
                .logVisit(new MessengerChoiceVisit("example.test", "203.0.113.8", "agent")));

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
            assertDoesNotThrow(() -> logger(builder, "http://127.0.0.1/", "super-token")
                    .logVisit(new MessengerChoiceVisit("example.test", "203.0.113.8", "agent")));
            assertDoesNotThrow(() -> logger(builder, "http://evil.example/internal", "super-token")
                    .logVisit(new MessengerChoiceVisit("example.test", "203.0.113.8", "agent")));
            assertDoesNotThrow(() -> logger(
                    builder,
                    "http://user:super-token@service-c:8000/internal/messenger-choice",
                    "super-token"
            ).logVisit(new MessengerChoiceVisit("example.test", "203.0.113.8", "agent")));
            assertDoesNotThrow(() -> new RestClientMessengerChoiceVisitLogger(
                    builder.build(),
                    LOG_URL,
                    "super-token",
                    List.of("logs.internal")
            ).logVisit(new MessengerChoiceVisit("example.test", "203.0.113.8", "agent")));

            server.verify();
            for (ILoggingEvent event : logs.list) {
                assertFalse(
                        event.getFormattedMessage().contains("super-token"),
                        event.getFormattedMessage()
                );
            }
        } finally {
            logback.detachAppender(logs);
            logback.setLevel(previous);
        }
    }

    @Test
    void itPostsWhenHostMatchesAllowListIgnoringCase() {
        RestClient.Builder builder = RestClient.builder();
        MockRestServiceServer server = MockRestServiceServer.bindTo(builder).build();
        String url = "https://Service-C/internal/messenger-choice";
        server.expect(requestTo(equalToIgnoringCase(url)))
                .andExpect(method(HttpMethod.POST))
                .andExpect(header(RestClientMessengerChoiceVisitLogger.TOKEN_HEADER, "secret"))
                .andExpect(jsonPath("$.host").value("example.test"))
                .andExpect(jsonPath("$.ip").value("203.0.113.8"))
                .andExpect(jsonPath("$.user_agent").value("agent"))
                .andRespond(withSuccess());

        assertDoesNotThrow(() -> new RestClientMessengerChoiceVisitLogger(
                builder.build(),
                url,
                "secret",
                List.of("service-c")
        ).logVisit(new MessengerChoiceVisit("example.test", "203.0.113.8", "agent")));

        server.verify();
    }

    private static RestClientMessengerChoiceVisitLogger logger(RestClient.Builder builder, String url, String token) {
        return new RestClientMessengerChoiceVisitLogger(builder.build(), url, token, List.of("service-c"));
    }
}
