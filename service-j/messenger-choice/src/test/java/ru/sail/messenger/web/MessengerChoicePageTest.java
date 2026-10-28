package ru.sail.messenger.web;

import static org.mockito.ArgumentMatchers.any;
import static org.mockito.Mockito.doThrow;
import static org.mockito.Mockito.verify;
import static org.mockito.Mockito.verifyNoInteractions;
import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.get;
import static org.springframework.test.web.servlet.result.MockMvcResultMatchers.content;
import static org.springframework.test.web.servlet.result.MockMvcResultMatchers.header;
import static org.springframework.test.web.servlet.result.MockMvcResultMatchers.redirectedUrl;
import static org.springframework.test.web.servlet.result.MockMvcResultMatchers.status;

import org.junit.jupiter.api.Test;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.boot.test.autoconfigure.web.servlet.AutoConfigureMockMvc;
import org.springframework.boot.test.context.SpringBootTest;
import org.springframework.http.HttpHeaders;
import org.springframework.http.MediaType;
import org.springframework.test.context.bean.override.mockito.MockitoBean;
import org.springframework.test.web.servlet.MockMvc;
import ru.sail.messenger.port.MessengerChoiceVisit;
import ru.sail.messenger.port.MessengerChoiceVisitLogger;
import ru.sail.messenger.support.MessengerChoiceTestApplication;

@SpringBootTest(classes = MessengerChoiceTestApplication.class)
@AutoConfigureMockMvc
class MessengerChoicePageTest {

    @Autowired
    private MockMvc mockMvc;

    @MockitoBean
    private MessengerChoiceVisitLogger visitLogger;

    @Test
    void itReturnsChoicePageAndLogsVisit() throws Exception {
        mockMvc.perform(get("/messenger")
                        .header("X-Forwarded-Host", "94-228-117-27.sslip.io")
                        .header("X-Real-IP", "203.0.113.9")
                        .header("User-Agent", "Mozilla"))
                .andExpect(status().isOk())
                .andExpect(header().string(HttpHeaders.CACHE_CONTROL, "no-store"))
                .andExpect(content().contentTypeCompatibleWith(MediaType.TEXT_HTML))
                .andExpect(content().string(org.hamcrest.Matchers.containsString("Telegram")))
                .andExpect(content().string(org.hamcrest.Matchers.containsString("https://t.me/obedov_bot")))
                .andExpect(content().string(org.hamcrest.Matchers.containsString("MAX")))
                .andExpect(content().string(org.hamcrest.Matchers.containsString("https://max.ru/id421816864057_bot")));

        verify(visitLogger).logVisit(new MessengerChoiceVisit(
                "94-228-117-27.sslip.io",
                "203.0.113.9",
                "Mozilla"
        ));
    }

    @Test
    void itLogsVisitForTrailingSlash() throws Exception {
        mockMvc.perform(get("/messenger/"))
                .andExpect(status().isOk())
                .andExpect(header().string(HttpHeaders.CACHE_CONTROL, "no-store"));

        verify(visitLogger).logVisit(any(MessengerChoiceVisit.class));
    }

    @Test
    void itReturnsChoicePageWhenVisitLogFails() throws Exception {
        doThrow(new IllegalStateException("service-c down")).when(visitLogger).logVisit(any());

        mockMvc.perform(get("/messenger"))
                .andExpect(status().isOk())
                .andExpect(content().string(org.hamcrest.Matchers.containsString("Telegram")));
    }

    @Test
    void itServesAssetsWithoutLogging() throws Exception {
        mockMvc.perform(get("/messenger/assets/probe.txt"))
                .andExpect(status().isOk())
                .andExpect(content().string(org.hamcrest.Matchers.containsString("probe-asset")));

        verifyNoInteractions(visitLogger);
    }

    @Test
    void itRedirectsIndexHtmlWithoutLogging() throws Exception {
        mockMvc.perform(get("/messenger/index.html"))
                .andExpect(status().isFound())
                .andExpect(redirectedUrl("/messenger"))
                .andExpect(header().string(HttpHeaders.CACHE_CONTROL, "no-store"));

        verifyNoInteractions(visitLogger);
    }
}
