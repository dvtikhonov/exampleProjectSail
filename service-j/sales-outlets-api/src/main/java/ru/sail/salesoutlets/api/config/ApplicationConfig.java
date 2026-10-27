package ru.sail.salesoutlets.api.config;

import java.time.Clock;
import java.util.List;
import com.fasterxml.jackson.databind.ObjectMapper;
import jakarta.servlet.http.HttpServletRequest;
import jakarta.validation.Validator;
import org.springframework.boot.web.servlet.FilterRegistrationBean;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;
import org.springframework.core.Ordered;
import org.springframework.data.jpa.repository.config.EnableJpaRepositories;
import org.springframework.boot.autoconfigure.domain.EntityScan;
import org.springframework.web.method.support.HandlerMethodArgumentResolver;
import org.springframework.web.servlet.config.annotation.WebMvcConfigurer;
import ru.sail.salesoutlets.api.auth.RequestGatewayAuthSession;
import ru.sail.salesoutlets.api.auth.RequestGatewayUserContext;
import ru.sail.salesoutlets.api.auth.TrustGatewayAuthFilter;
import ru.sail.salesoutlets.api.web.IndexSalesOutletsRequestResolver;
import ru.sail.salesoutlets.application.ConfigSalesOutletTableMetaProvider;
import ru.sail.salesoutlets.application.DefaultSalesOutletService;
import ru.sail.salesoutlets.application.StaticSalesOutletsMetadataRepository;
import ru.sail.salesoutlets.domain.auth.GatewayAuthSession;
import ru.sail.salesoutlets.domain.auth.GatewayUserContext;
import ru.sail.salesoutlets.domain.auth.GatewayUserResolver;
import ru.sail.salesoutlets.domain.port.SalesOutletRepository;
import ru.sail.salesoutlets.domain.port.SalesOutletService;
import ru.sail.salesoutlets.domain.port.SalesOutletTableMetaProvider;
import ru.sail.salesoutlets.domain.port.SalesOutletsMetadataRepository;
import ru.sail.salesoutlets.infrastructure.auth.JpaGatewayUserResolver;
import ru.sail.salesoutlets.infrastructure.persistence.JpaSalesOutletQueryApplicator;
import ru.sail.salesoutlets.infrastructure.persistence.JpaSalesOutletRepository;
import ru.sail.salesoutlets.infrastructure.persistence.SalesOutletSpringDataRepository;
import ru.sail.salesoutlets.infrastructure.persistence.UserSpringDataRepository;

/**
 * DI-конфигурация: порты домена → реализации application/infrastructure.
 */
@Configuration
@EnableJpaRepositories(basePackages = "ru.sail.salesoutlets.infrastructure.persistence")
@EntityScan(basePackages = "ru.sail.salesoutlets.infrastructure.persistence")
public class ApplicationConfig {

    @Bean
    SalesOutletsMetadataRepository salesOutletsMetadataRepository() {
        return new StaticSalesOutletsMetadataRepository();
    }

    @Bean
    SalesOutletTableMetaProvider salesOutletTableMetaProvider(SalesOutletsMetadataRepository metadataRepository) {
        return new ConfigSalesOutletTableMetaProvider(metadataRepository);
    }

    @Bean
    JpaSalesOutletQueryApplicator jpaSalesOutletQueryApplicator() {
        return new JpaSalesOutletQueryApplicator();
    }

    @Bean
    SalesOutletRepository salesOutletRepository(
            SalesOutletSpringDataRepository springDataRepository,
            JpaSalesOutletQueryApplicator queryApplicator
    ) {
        return new JpaSalesOutletRepository(springDataRepository, queryApplicator);
    }

    @Bean
    Clock clock() {
        return Clock.systemUTC();
    }

    @Bean
    SalesOutletService salesOutletService(
            SalesOutletRepository salesOutletRepository,
            GatewayUserContext gatewayUserContext,
            Clock clock
    ) {
        return new DefaultSalesOutletService(salesOutletRepository, gatewayUserContext, clock);
    }

    @Bean
    GatewayUserResolver gatewayUserResolver(UserSpringDataRepository userSpringDataRepository) {
        return new JpaGatewayUserResolver(userSpringDataRepository);
    }

    @Bean
    GatewayAuthSession gatewayAuthSession(HttpServletRequest request) {
        return new RequestGatewayAuthSession(request);
    }

    @Bean
    GatewayUserContext gatewayUserContext(HttpServletRequest request) {
        return new RequestGatewayUserContext(request);
    }

    @Bean
    FilterRegistrationBean<TrustGatewayAuthFilter> trustGatewayAuthFilter(
            GatewayUserResolver gatewayUserResolver,
            ObjectMapper objectMapper
    ) {
        FilterRegistrationBean<TrustGatewayAuthFilter> registration = new FilterRegistrationBean<>();
        registration.setFilter(new TrustGatewayAuthFilter(gatewayUserResolver, objectMapper));
        registration.addUrlPatterns("/api/*");
        registration.setOrder(Ordered.HIGHEST_PRECEDENCE + 20);
        registration.setName("trustGatewayAuthFilter");
        return registration;
    }

    /**
     * Валидация GET index на границе MVC, до входа в контроллер.
     */
    @Bean
    WebMvcConfigurer indexSalesOutletsRequestResolverConfigurer(Validator validator) {
        IndexSalesOutletsRequestResolver resolver = new IndexSalesOutletsRequestResolver(validator);
        return new WebMvcConfigurer() {
            @Override
            public void addArgumentResolvers(List<HandlerMethodArgumentResolver> resolvers) {
                resolvers.add(resolver);
            }
        };
    }
}
