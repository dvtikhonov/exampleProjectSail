package ru.sail.messenger.http;

import static org.junit.jupiter.api.Assertions.assertDoesNotThrow;
import static org.springframework.test.web.client.match.MockRestRequestMatchers.content;
import static org.springframework.test.web.client.match.MockRestRequestMatchers.header;
import static org.springframework.test.web.client.match.MockRestRequestMatchers.jsonPath;
import static org.springframework.test.web.client.match.MockRestRequestMatchers.method;
import static org.springframework.test.web.client.match.MockRestRequestMatchers.requestTo;
import static org.springframework.test.web.client.response.MockRestResponseCreators.withStatus;
import static org.springframework.test.web.client.response.MockRestResponseCreators.withSuccess;

import java.io.IOException;
import org.junit.jupiter.api.Test;
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

    private static RestClientMessengerChoiceVisitLogger logger(RestClient.Builder builder, String url, String token) {
        return new RestClientMessengerChoiceVisitLogger(builder.build(), url, token);
    }
}
