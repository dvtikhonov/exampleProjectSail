package ru.sail.messenger.port;

/**
 * Порт записи визита страницы выбора мессенджера.
 * Реализация не должна бросать наружу сбой удалённого вызова: страница открывается в любом случае.
 */
public interface MessengerChoiceVisitLogger {

    /**
     * Сообщает о заходе на {@code /messenger}.
     *
     * @param visit хост, адрес и User-Agent этого запроса
     */
    void logVisit(MessengerChoiceVisit visit);
}
