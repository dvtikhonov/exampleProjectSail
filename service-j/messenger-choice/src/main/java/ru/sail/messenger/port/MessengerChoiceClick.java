package ru.sail.messenger.port;

/**
 * Клик по одному из двух мессенджеров.
 * Ссылка на бота в снимок не входит: её знает только каталог адресов.
 *
 * @param visit     хост, адрес и User-Agent запроса перехода
 * @param messenger выбранный мессенджер
 */
public record MessengerChoiceClick(MessengerChoiceVisit visit, ChosenMessenger messenger) {

    public MessengerChoiceClick {
        if (visit == null) {
            throw new IllegalArgumentException("visit");
        }
        if (messenger == null) {
            throw new IllegalArgumentException("messenger");
        }
    }
}
