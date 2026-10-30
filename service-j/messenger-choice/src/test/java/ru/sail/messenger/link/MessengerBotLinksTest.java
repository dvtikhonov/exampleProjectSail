package ru.sail.messenger.link;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertTrue;

import java.net.URI;
import org.junit.jupiter.api.Test;
import ru.sail.messenger.port.ChosenMessenger;

class MessengerBotLinksTest {

    private final MessengerBotLinks links = new MessengerBotLinks();

    @Test
    void itKeepsEachBotAddressOnItsOwnMessenger() {
        URI telegram = links.location(ChosenMessenger.TELEGRAM);
        URI max = links.location(ChosenMessenger.MAX);

        assertEquals("https", telegram.getScheme());
        assertEquals("t.me", telegram.getHost());
        assertEquals("/obedov_bot", telegram.getPath());
        assertEquals("https", max.getScheme());
        assertEquals("max.ru", max.getHost());
        assertEquals("/id421816864057_bot", max.getPath());
        assertEquals("start=register", max.getQuery());
        assertTrue(ChosenMessenger.fromCode("obedov_bot").isEmpty());
        assertTrue(ChosenMessenger.fromCode("id421816864057_bot").isEmpty());
        assertEquals(ChosenMessenger.TELEGRAM, ChosenMessenger.fromCode("telegram").orElseThrow());
        assertEquals(ChosenMessenger.MAX, ChosenMessenger.fromCode("max").orElseThrow());
    }
}
