package ru.sail.salesoutlets.api.web;

import org.springframework.http.MediaType;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.RestController;

import java.util.Map;

/**
 * Smoke endpoint for compose/gateway checks (skeleton).
 */
@RestController
public class HealthController {

    @GetMapping(value = "/api/health", produces = MediaType.APPLICATION_JSON_VALUE)
    public Map<String, String> health() {
        return Map.of("status", "UP", "service", "service-j");
    }
}
