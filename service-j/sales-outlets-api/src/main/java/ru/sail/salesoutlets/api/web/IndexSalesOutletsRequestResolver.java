package ru.sail.salesoutlets.api.web;

import jakarta.servlet.http.HttpServletRequest;
import jakarta.validation.ConstraintViolation;
import jakarta.validation.ConstraintViolationException;
import jakarta.validation.Validator;
import java.util.Set;
import org.springframework.core.MethodParameter;
import org.springframework.web.bind.support.WebDataBinderFactory;
import org.springframework.web.context.request.NativeWebRequest;
import org.springframework.web.method.support.HandlerMethodArgumentResolver;
import org.springframework.web.method.support.ModelAndViewContainer;
import ru.sail.salesoutlets.api.web.request.IndexSalesOutletsRequest;

/**
 * Граница API для GET {@code /api/sales-outlets}: разбирает query и проверяет его
 * до вызова use-case index.
 * <p>
 * Парсинг ({@code column_filters[key]}, {@code columns[]}) остаётся в
 * {@link IndexSalesOutletsRequest#fromHttpRequest(HttpServletRequest)}.
 * Непустой набор нарушений Bean Validation прерывает разбор аргумента
 * {@link ConstraintViolationException}; тело 422 ({@code message}, {@code errors})
 * собирает {@link ApiExceptionHandler}. Контроллер при этом не вызывается.
 */
public final class IndexSalesOutletsRequestResolver implements HandlerMethodArgumentResolver {

    private final Validator validator;

    /**
     * @param validator валидатор Spring (те же ограничения, что на полях {@link IndexSalesOutletsRequest})
     */
    public IndexSalesOutletsRequestResolver(Validator validator) {
        this.validator = validator;
    }

    @Override
    public boolean supportsParameter(MethodParameter parameter) {
        return IndexSalesOutletsRequest.class.equals(parameter.getParameterType());
    }

    @Override
    public Object resolveArgument(
            MethodParameter parameter,
            ModelAndViewContainer mavContainer,
            NativeWebRequest webRequest,
            WebDataBinderFactory binderFactory
    ) {
        HttpServletRequest httpRequest = webRequest.getNativeRequest(HttpServletRequest.class);
        if (httpRequest == null) {
            throw new IllegalStateException("Для разбора query index нужен HttpServletRequest");
        }
        IndexSalesOutletsRequest request = IndexSalesOutletsRequest.fromHttpRequest(httpRequest);
        Set<ConstraintViolation<IndexSalesOutletsRequest>> violations = validator.validate(request);
        if (!violations.isEmpty()) {
            throw new ConstraintViolationException(violations);
        }
        return request;
    }
}
