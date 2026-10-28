package ru.sail.messenger.port;

import java.util.Optional;

/**
 * Какой мессенджер выбрали на странице.
 * Код уходит в лог. Адрес бота здесь не хранится.
 */
public enum ChosenMessenger {

    TELEGRAM("telegram"),
    MAX("max");

    private final String code;

    ChosenMessenger(String code) {
        this.code = code;
    }

    /**
     * Значение поля {@code messenger} в теле записи клика.
     */
    public String code() {
        return code;
    }

    /**
     * @param code {@code telegram} или {@code max}; ник бота и URL не подходят
     */
    public static Optional<ChosenMessenger> fromCode(String code) {
        if (code == null || code.isEmpty()) {
            return Optional.empty();
        }
        for (ChosenMessenger messenger : values()) {
            if (messenger.code.equals(code)) {
                return Optional.of(messenger);
            }
        }
        return Optional.empty();
    }
}
