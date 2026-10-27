package ru.sail.salesoutlets.domain.auth;

/**
 * Идентичность gateway-пользователя (из заголовка {@code X-User-Id}).
 *
 * @param id идентификатор пользователя в таблице {@code users}
 */
public record GatewayUser(long id) {
}
