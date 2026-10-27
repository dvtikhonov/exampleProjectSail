package ru.sail.messenger.web;

import jakarta.servlet.http.HttpServletRequest;
import java.net.URI;
import java.nio.charset.StandardCharsets;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.http.CacheControl;
import org.springframework.http.HttpStatus;
import org.springframework.http.MediaType;
import org.springframework.http.ResponseEntity;
import org.springframework.stereotype.Controller;
import org.springframework.web.bind.annotation.GetMapping;
import ru.sail.messenger.port.MessengerChoiceVisitLogger;

/**
 * Отдаёт страницу выбора мессенджера и не пишет в лог запросы статики.
 */
@Controller
public class MessengerChoicePageController {

    private static final String PAGE = "/messenger";
    private static final MediaType HTML_UTF8 = new MediaType("text", "html", StandardCharsets.UTF_8);
    private static final Logger log = LoggerFactory.getLogger(MessengerChoicePageController.class);

    private final MessengerChoiceVisitLogger visitLogger;
    private final MessengerChoiceVisitResolver visitResolver;
    private final ClasspathMessengerChoicePage page;

    public MessengerChoicePageController(
            MessengerChoiceVisitLogger visitLogger,
            MessengerChoiceVisitResolver visitResolver,
            ClasspathMessengerChoicePage page
    ) {
        this.visitLogger = visitLogger;
        this.visitResolver = visitResolver;
        this.page = page;
    }

    /**
     * Страница без кэша: каждый заход снова доходит до записи визита.
     */
    @GetMapping(value = {PAGE, PAGE + "/"}, produces = MediaType.TEXT_HTML_VALUE)
    public ResponseEntity<String> show(HttpServletRequest request) {
        logVisit(request);
        return html();
    }

    /**
     * Файл сборки не отдаётся напрямую, иначе визит не записывается.
     */
    @GetMapping(PAGE + "/index.html")
    public ResponseEntity<Void> redirectBuiltIndex() {
        return ResponseEntity.status(HttpStatus.FOUND)
                .location(URI.create(PAGE))
                .cacheControl(CacheControl.noStore())
                .build();
    }

    private void logVisit(HttpServletRequest request) {
        try {
            visitLogger.logVisit(visitResolver.resolve(request));
        } catch (RuntimeException ex) {
            log.error("Messenger choice page is served without a visit log", ex);
        }
    }

    private ResponseEntity<String> html() {
        return ResponseEntity.ok()
                .cacheControl(CacheControl.noStore())
                .contentType(HTML_UTF8)
                .body(page.html());
    }
}
