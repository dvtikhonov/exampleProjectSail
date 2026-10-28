package ru.sail.messenger.config;

import java.util.ArrayList;
import java.util.List;
import org.springframework.boot.context.properties.ConfigurationProperties;

/**
 * Адреса, токен и allow-list хостов записи в service-c.
 * {@code messenger.choice.log-url} — визит страницы,
 * {@code messenger.choice.click-log-url} — клик по мессенджеру,
 * {@code messenger.choice.log-token},
 * {@code messenger.choice.allowed-hosts} (по умолчанию только {@code service-c}).
 */
@ConfigurationProperties(prefix = "messenger.choice")
public class MessengerChoiceProperties {

    private String logUrl = "";
    private String clickLogUrl = "";
    private String logToken = "";

    /**
     * Имена хостов без схемы и порта. Сравнение при отправке без учёта регистра.
     * Пустой список запрещает любой URL. Если свойство не задано, остаётся {@code service-c}.
     */
    private List<String> allowedHosts = new ArrayList<>(List.of("service-c"));

    public String getLogUrl() {
        return logUrl;
    }

    public void setLogUrl(String logUrl) {
        this.logUrl = logUrl == null ? "" : logUrl;
    }

    public String getClickLogUrl() {
        return clickLogUrl;
    }

    public void setClickLogUrl(String clickLogUrl) {
        this.clickLogUrl = clickLogUrl == null ? "" : clickLogUrl;
    }

    public String getLogToken() {
        return logToken;
    }

    public void setLogToken(String logToken) {
        this.logToken = logToken == null ? "" : logToken;
    }

    public List<String> getAllowedHosts() {
        return allowedHosts;
    }

    public void setAllowedHosts(List<String> allowedHosts) {
        this.allowedHosts = allowedHosts == null ? new ArrayList<>() : allowedHosts;
    }
}
