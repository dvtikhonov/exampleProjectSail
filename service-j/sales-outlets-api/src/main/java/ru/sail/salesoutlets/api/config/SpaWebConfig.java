package ru.sail.salesoutlets.api.config;

import org.springframework.context.annotation.Configuration;
import org.springframework.core.io.ClassPathResource;
import org.springframework.core.io.Resource;
import org.springframework.web.servlet.config.annotation.ResourceHandlerRegistry;
import org.springframework.web.servlet.config.annotation.ViewControllerRegistry;
import org.springframework.web.servlet.config.annotation.WebMvcConfigurer;
import org.springframework.web.servlet.resource.PathResourceResolver;

import java.io.IOException;

/**
 * Serves Vue SPA under {@code /objects-sales-outlets-4/} (history mode fallback to index.html).
 */
@Configuration
public class SpaWebConfig implements WebMvcConfigurer {

    private static final String SPA_BASE = "/objects-sales-outlets-4";
    private static final String SPA_INDEX = "forward:/objects-sales-outlets-4/index.html";

    @Override
    public void addViewControllers(ViewControllerRegistry registry) {
        registry.addViewController(SPA_BASE).setViewName(SPA_INDEX);
        registry.addViewController(SPA_BASE + "/").setViewName(SPA_INDEX);
    }

    @Override
    public void addResourceHandlers(ResourceHandlerRegistry registry) {
        registry.addResourceHandler(SPA_BASE + "/**")
                .addResourceLocations("classpath:/static/objects-sales-outlets-4/")
                .resourceChain(true)
                .addResolver(new PathResourceResolver() {
                    @Override
                    protected Resource getResource(String resourcePath, Resource location) throws IOException {
                        Resource requested = location.createRelative(resourcePath);
                        if (requested.exists() && requested.isReadable()) {
                            return requested;
                        }
                        return new ClassPathResource("static/objects-sales-outlets-4/index.html");
                    }
                });
    }
}
