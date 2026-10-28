package ru.sail.messenger.config;

import java.time.Duration;
import org.springframework.boot.context.properties.EnableConfigurationProperties;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;
import org.springframework.http.client.SimpleClientHttpRequestFactory;
import org.springframework.web.client.RestClient;
import org.springframework.web.servlet.config.annotation.ResourceHandlerRegistry;
import org.springframework.web.servlet.config.annotation.WebMvcConfigurer;
import ru.sail.messenger.http.RestClientMessengerChoiceClickLogger;
import ru.sail.messenger.http.RestClientMessengerChoiceVisitLogger;
import ru.sail.messenger.link.MessengerBotLinks;
import ru.sail.messenger.port.MessengerChoiceClickLogger;
import ru.sail.messenger.port.MessengerChoiceVisitLogger;
import ru.sail.messenger.web.ClasspathMessengerChoicePage;
import ru.sail.messenger.web.MessengerChoiceVisitResolver;

/**
 * Связывает порты визита и клика с RestClient, отдаёт каталог адресов ботов
 * и ассеты страницы без участия контроллера страницы.
 */
@Configuration
@EnableConfigurationProperties(MessengerChoiceProperties.class)
public class MessengerChoiceConfiguration {

    private static final Duration LOG_TIMEOUT = Duration.ofSeconds(2);

    @Bean
    MessengerChoiceVisitResolver messengerChoiceVisitResolver() {
        return new MessengerChoiceVisitResolver();
    }

    @Bean
    ClasspathMessengerChoicePage classpathMessengerChoicePage() {
        return new ClasspathMessengerChoicePage();
    }

    @Bean
    MessengerBotLinks messengerBotLinks() {
        return new MessengerBotLinks();
    }

    @Bean
    MessengerChoiceVisitLogger messengerChoiceVisitLogger(MessengerChoiceProperties properties) {
        return new RestClientMessengerChoiceVisitLogger(
                newRestClient(),
                properties.getLogUrl(),
                properties.getLogToken(),
                properties.getAllowedHosts()
        );
    }

    @Bean
    MessengerChoiceClickLogger messengerChoiceClickLogger(MessengerChoiceProperties properties) {
        return new RestClientMessengerChoiceClickLogger(
                newRestClient(),
                properties.getClickLogUrl(),
                properties.getLogToken(),
                properties.getAllowedHosts()
        );
    }

    private static RestClient newRestClient() {
        SimpleClientHttpRequestFactory requestFactory = new SimpleClientHttpRequestFactory();
        requestFactory.setConnectTimeout(LOG_TIMEOUT);
        requestFactory.setReadTimeout(LOG_TIMEOUT);
        return RestClient.builder()
                .requestFactory(requestFactory)
                .build();
    }

    /**
     * {@code /messenger/assets/**} из classpath этого модуля. В лог визита эти запросы не попадают.
     */
    @Bean
    WebMvcConfigurer messengerChoiceAssetResources() {
        return new WebMvcConfigurer() {
            @Override
            public void addResourceHandlers(ResourceHandlerRegistry registry) {
                registry.addResourceHandler("/messenger/assets/**")
                        .addResourceLocations("classpath:/static/messenger/assets/");
            }
        };
    }
}
