package ru.sail.salesoutlets.infrastructure.auth;

import java.util.Optional;
import java.util.UUID;
import org.springframework.security.crypto.bcrypt.BCryptPasswordEncoder;
import org.springframework.security.crypto.password.PasswordEncoder;
import org.springframework.transaction.annotation.Transactional;
import ru.sail.salesoutlets.domain.auth.GatewayUser;
import ru.sail.salesoutlets.domain.auth.GatewayUserResolver;
import ru.sail.salesoutlets.infrastructure.persistence.UserJpaEntity;
import ru.sail.salesoutlets.infrastructure.persistence.UserSpringDataRepository;

/**
 * Разрешает gateway-пользователя по id: читает {@code users} или создаёт техническую запись.
 */
@Transactional
public class JpaGatewayUserResolver implements GatewayUserResolver {

    private final UserSpringDataRepository userRepository;
    private final PasswordEncoder passwordEncoder;

    public JpaGatewayUserResolver(UserSpringDataRepository userRepository) {
        this(userRepository, new BCryptPasswordEncoder());
    }

    public JpaGatewayUserResolver(UserSpringDataRepository userRepository, PasswordEncoder passwordEncoder) {
        this.userRepository = userRepository;
        this.passwordEncoder = passwordEncoder;
    }

    @Override
    public Optional<GatewayUser> resolveById(long userId) {
        if (userId <= 0) {
            return Optional.empty();
        }
        UserJpaEntity user = userRepository.findById(userId).orElseGet(() -> provisionGatewayUser(userId));
        return Optional.of(new GatewayUser(user.getId()));
    }

    private UserJpaEntity provisionGatewayUser(long userId) {
        UserJpaEntity user = new UserJpaEntity(userId);
        user.setName("Gateway User " + userId);
        user.setEmail("gateway-user-" + userId + "@gateway.local");
        user.setPassword(passwordEncoder.encode(UUID.randomUUID().toString()));
        return userRepository.save(user);
    }
}
