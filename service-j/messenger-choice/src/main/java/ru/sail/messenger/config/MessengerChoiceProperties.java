package ru.sail.messenger.config;

import org.springframework.boot.context.properties.ConfigurationProperties;

/**
 * Адрес и токен внутреннего вызова service-c ({@code MESSENGER_CHOICE_LOG_URL}, {@code MESSENGER_CHOICE_LOG_TOKEN}).
 */
@ConfigurationProperties(prefix = "messenger.choice")
public class MessengerChoiceProperties {

    private String logUrl = "";
    private String logToken = "";

    public String getLogUrl() {
        return logUrl;
    }

    public void setLogUrl(String logUrl) {
        this.logUrl = logUrl == null ? "" : logUrl;
    }

    public String getLogToken() {
        return logToken;
    }

    public void setLogToken(String logToken) {
        this.logToken = logToken == null ? "" : logToken;
    }
}
