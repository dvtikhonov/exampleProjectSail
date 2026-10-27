package ru.sail.messenger.support;

import org.springframework.boot.SpringBootConfiguration;
import org.springframework.boot.autoconfigure.EnableAutoConfiguration;
import org.springframework.context.annotation.ComponentScan;

/**
 * Контекст MockMvc только для модуля messenger-choice, без торговых точек и БД.
 */
@SpringBootConfiguration
@EnableAutoConfiguration
@ComponentScan(basePackages = {
        "ru.sail.messenger.web",
        "ru.sail.messenger.config"
})
public class MessengerChoiceTestApplication {
}
