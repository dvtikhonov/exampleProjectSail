package ru.sail.messenger.link;

import java.net.URI;
import java.util.Objects;
import ru.sail.messenger.port.ChosenMessenger;

/**
 * Публичные адреса двух ботов.
 * Единственное место, где записаны {@code t.me/..._bot} и {@code max.ru/..._bot}.
 * Лог клика этот класс не вызывает и код мессенджера в URL не превращает обратно.
 */
public final class MessengerBotLinks {

    private static final URI TELEGRAM = URI.create("https://t.me/obedov_bot");
    private static final URI MAX = URI.create("https://max.ru/id421816864057_bot");

    /**
     * Адрес бота для редиректа. Аргумент — уже разобранный выбор, не строка из запроса.
     */
    public URI location(ChosenMessenger messenger) {
        Objects.requireNonNull(messenger, "messenger");
        return switch (messenger) {
            case TELEGRAM -> TELEGRAM;
            case MAX -> MAX;
        };
    }
}
