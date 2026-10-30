package ru.sail.messenger.web;

import static org.mockito.ArgumentMatchers.any;
import static org.mockito.Mockito.doThrow;
import static org.mockito.Mockito.verify;
import static org.mockito.Mockito.verifyNoInteractions;
import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.get;
import static org.springframework.test.web.servlet.result.MockMvcResultMatchers.header;
import static org.springframework.test.web.servlet.result.MockMvcResultMatchers.redirectedUrl;
import static org.springframework.test.web.servlet.result.MockMvcResultMatchers.status;

import org.junit.jupiter.api.Test;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.boot.test.autoconfigure.web.servlet.AutoConfigureMockMvc;
import org.springframework.boot.test.context.SpringBootTest;
import org.springframework.http.HttpHeaders;
import org.springframework.test.context.bean.override.mockito.MockitoBean;
import org.springframework.test.web.servlet.MockMvc;
import ru.sail.messenger.port.ChosenMessenger;
import ru.sail.messenger.port.MessengerChoiceClick;
import ru.sail.messenger.port.MessengerChoiceClickLogger;
import ru.sail.messenger.port.MessengerChoiceVisit;
import ru.sail.messenger.port.MessengerChoiceVisitLogger;
import ru.sail.messenger.support.MessengerChoiceTestApplication;

@SpringBootTest(classes = MessengerChoiceTestApplication.class)
@AutoConfigureMockMvc
class MessengerChoiceLinkTest {

    @Autowired
    private MockMvc mockMvc;

    @MockitoBean
    private MessengerChoiceClickLogger clickLogger;

    @MockitoBean
    private MessengerChoiceVisitLogger visitLogger;

    @Test
    void itLogsTelegramChoiceAndRedirectsToTheBot() throws Exception {
        mockMvc.perform(get("/messenger/go/telegram")
                        .header("X-Forwarded-Host", "94-228-117-27.sslip.io")
                        .header("X-Real-IP", "203.0.113.9")
                        .header("User-Agent", "Mozilla"))
                .andExpect(status().isFound())
                .andExpect(redirectedUrl("https://t.me/obedov_bot"))
                .andExpect(header().string(HttpHeaders.CACHE_CONTROL, "no-store"));

        verify(clickLogger).logClick(new MessengerChoiceClick(
                new MessengerChoiceVisit("94-228-117-27.sslip.io", "203.0.113.9", "Mozilla"),
                ChosenMessenger.TELEGRAM
        ));
        verifyNoInteractions(visitLogger);
    }

    @Test
    void itLogsMaxChoiceAndRedirectsToTheBot() throws Exception {
        mockMvc.perform(get("/messenger/go/max"))
                .andExpect(status().isFound())
                .andExpect(redirectedUrl("https://max.ru/id421816864057_bot?start=register"))
                .andExpect(header().string(HttpHeaders.CACHE_CONTROL, "no-store"));

        verify(clickLogger).logClick(new MessengerChoiceClick(
                new MessengerChoiceVisit("", null, ""),
                ChosenMessenger.MAX
        ));
        verifyNoInteractions(visitLogger);
    }

    @Test
    void itRedirectsWhenClickLogFails() throws Exception {
        doThrow(new IllegalStateException("service-c down")).when(clickLogger).logClick(any());

        mockMvc.perform(get("/messenger/go/telegram"))
                .andExpect(status().isFound())
                .andExpect(redirectedUrl("https://t.me/obedov_bot"));
    }

    @Test
    void itDoesNotRedirectWhenMessengerIsNotAChoice() throws Exception {
        mockMvc.perform(get("/messenger/go/obedov_bot"))
                .andExpect(status().isNotFound());

        verifyNoInteractions(clickLogger);
        verifyNoInteractions(visitLogger);
    }
}
