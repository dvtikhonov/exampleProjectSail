package ru.sail.messenger.web;

import java.io.IOException;
import java.io.InputStream;
import java.nio.charset.StandardCharsets;
import org.springframework.core.io.ClassPathResource;
import org.springframework.util.StreamUtils;

/**
 * HTML сборки messenger-frontend ({@code classpath:static/messenger/index.html}).
 * В образе файл подменяется результатом Vite. Ссылки ведут на {@code /messenger/go/telegram}
 * и {@code /messenger/go/max}; адреса ботов в страницу не входят.
 */
public final class ClasspathMessengerChoicePage {

    static final String LOCATION = "static/messenger/index.html";

    private final String html;

    public ClasspathMessengerChoicePage() {
        this.html = read();
    }

    /** Готовая страница, UTF-8. */
    public String html() {
        return html;
    }

    private static String read() {
        ClassPathResource resource = new ClassPathResource(LOCATION);
        if (!resource.exists()) {
            throw new IllegalStateException("Messenger choice page is missing: " + LOCATION);
        }
        try (InputStream input = resource.getInputStream()) {
            return StreamUtils.copyToString(input, StandardCharsets.UTF_8);
        } catch (IOException ex) {
            throw new IllegalStateException("Messenger choice page cannot be read: " + LOCATION, ex);
        }
    }
}
