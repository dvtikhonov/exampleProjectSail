package ru.sail.messenger.port;

/**
 * Снимок визита страницы выбора мессенджера.
 * Секретов и initData здесь нет: только то, что уходит в service-c.
 *
 * @param host      публичный хост без порта
 * @param ip        IP клиента или {@code null}, если заголовков адреса не было
 * @param userAgent заголовок User-Agent; пустая строка, если его не было
 */
public record MessengerChoiceVisit(String host, String ip, String userAgent) {

    public MessengerChoiceVisit {
        host = host == null ? "" : host;
        userAgent = userAgent == null ? "" : userAgent;
    }
}
