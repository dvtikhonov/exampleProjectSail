package ru.sail.messenger.web;

import jakarta.servlet.http.HttpServletRequest;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.http.CacheControl;
import org.springframework.http.HttpStatus;
import org.springframework.http.ResponseEntity;
import org.springframework.stereotype.Controller;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.server.ResponseStatusException;
import ru.sail.messenger.link.MessengerBotLinks;
import ru.sail.messenger.port.ChosenMessenger;
import ru.sail.messenger.port.MessengerChoiceClick;
import ru.sail.messenger.port.MessengerChoiceClickLogger;

/**
 * Переход к боту после выбора на странице.
 * Пишет в лог только код мессенджера. Адрес {@code *_bot} берёт у {@link MessengerBotLinks} и сам его не хранит.
 */
@Controller
public class MessengerChoiceLinkController {

    private static final Logger log = LoggerFactory.getLogger(MessengerChoiceLinkController.class);

    private final MessengerChoiceClickLogger clickLogger;
    private final MessengerChoiceVisitResolver visitResolver;
    private final MessengerBotLinks botLinks;

    public MessengerChoiceLinkController(
            MessengerChoiceClickLogger clickLogger,
            MessengerChoiceVisitResolver visitResolver,
            MessengerBotLinks botLinks
    ) {
        this.clickLogger = clickLogger;
        this.visitResolver = visitResolver;
        this.botLinks = botLinks;
    }

    /**
     * Без кэша: повторный клик снова доходит до записи.
     * Сбой лога не отменяет редирект.
     */
    @GetMapping("/messenger/go/{messenger:telegram|max}")
    public ResponseEntity<Void> follow(HttpServletRequest request, @PathVariable String messenger) {
        ChosenMessenger choice = ChosenMessenger.fromCode(messenger)
                .orElseThrow(() -> new ResponseStatusException(HttpStatus.NOT_FOUND));
        logClick(request, choice);
        return ResponseEntity.status(HttpStatus.FOUND)
                .location(botLinks.location(choice))
                .cacheControl(CacheControl.noStore())
                .build();
    }

    private void logClick(HttpServletRequest request, ChosenMessenger choice) {
        try {
            clickLogger.logClick(new MessengerChoiceClick(visitResolver.resolve(request), choice));
        } catch (RuntimeException ex) {
            log.error("Messenger choice link is followed without a click log", ex);
        }
    }
}
