package ru.sail.messenger.web;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertNull;

import org.junit.jupiter.api.Test;
import org.springframework.mock.web.MockHttpServletRequest;
import ru.sail.messenger.port.MessengerChoiceVisit;

class MessengerChoiceVisitResolverTest {

    private final MessengerChoiceVisitResolver resolver = new MessengerChoiceVisitResolver();

    @Test
    void itPrefersForwardedHostAndRealIp() {
        MockHttpServletRequest request = new MockHttpServletRequest();
        request.addHeader("X-Forwarded-Host", "public.example:443, internal");
        request.addHeader("Host", "service-j:8080");
        request.addHeader("X-Real-IP", "203.0.113.5");
        request.addHeader("X-Forwarded-For", "198.51.100.2, 10.0.0.1");
        request.addHeader("User-Agent", "Agent");

        MessengerChoiceVisit visit = resolver.resolve(request);

        assertEquals("public.example", visit.host());
        assertEquals("203.0.113.5", visit.ip());
        assertEquals("Agent", visit.userAgent());
    }

    @Test
    void itFallsBackToHostHeaderAndForwardedFor() {
        MockHttpServletRequest request = new MockHttpServletRequest();
        request.addHeader("Host", "94-228-117-27.sslip.io");
        request.addHeader("X-Forwarded-For", " 198.51.100.8 , 10.0.0.4");

        MessengerChoiceVisit visit = resolver.resolve(request);

        assertEquals("94-228-117-27.sslip.io", visit.host());
        assertEquals("198.51.100.8", visit.ip());
        assertEquals("", visit.userAgent());
    }

    @Test
    void itReturnsNullIpWhenAddressHeadersAreMissing() {
        MockHttpServletRequest request = new MockHttpServletRequest();
        request.addHeader("Host", "localhost");

        assertNull(resolver.resolve(request).ip());
    }

    @Test
    void itLimitsUserAgentLength() {
        MockHttpServletRequest request = new MockHttpServletRequest();
        request.addHeader("Host", "localhost");
        request.addHeader("User-Agent", "u".repeat(200));

        assertEquals(MessengerChoiceVisitResolver.USER_AGENT_MAX_LENGTH, resolver.resolve(request).userAgent().length());
    }
}
