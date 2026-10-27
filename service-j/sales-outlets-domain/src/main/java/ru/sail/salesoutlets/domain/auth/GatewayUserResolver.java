package ru.sail.salesoutlets.domain.auth;

import java.util.Optional;

/**
 * Разрешает gateway-пользователя по id из заголовка {@code X-User-Id}.
 * HTTP-адаптер извлекает и парсит заголовок; порт не зависит от servlet API.
 */
public interface GatewayUserResolver {

    /**
     * Находит или provision-ит пользователя по id.
     *
     * @return empty, если пользователь не может быть разрешён
     */
    Optional<GatewayUser> resolveById(long userId);
}
