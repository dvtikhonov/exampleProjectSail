package ru.sail.messenger.port;

/**
 * Порт записи клика по мессенджеру.
 * Реализация не должна бросать наружу сбой удалённого вызова: переход к боту выполняется в любом случае.
 */
public interface MessengerChoiceClickLogger {

    /**
     * Сообщает, что открыли переход {@code /messenger/go/{messenger}}.
     *
     * @param click снимок запроса и выбранный мессенджер, без URL бота
     */
    void logClick(MessengerChoiceClick click);
}
