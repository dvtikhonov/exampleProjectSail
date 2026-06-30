<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Contract\Auth\GatewayUserResolverInterface;
use App\DTO\Auth\GatewayUserDto;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Разрешает пользователя по заголовку X-User-Id.
 * При отсутствии записи в БД создаёт технического gateway-пользователя.
 */
class DoctrineGatewayUserResolver implements GatewayUserResolverInterface
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function resolveFromRequest(Request $request): ?GatewayUserDto
    {
        $userId = $request->headers->get('X-User-Id');

        if ($userId === null || $userId === '' || ! is_numeric($userId) || (int) $userId <= 0) {
            return null;
        }

        $userId = (int) $userId;
        $user = $this->userRepository->find($userId) ?? $this->provisionGatewayUser($userId);

        return new GatewayUserDto(user: $user);
    }

    /** Создаёт запись users с фиксированным id из gateway. */
    private function provisionGatewayUser(int $userId): User
    {
        $user = (new User($userId))
            ->setName("Gateway User {$userId}")
            ->setEmail("gateway-user-{$userId}@gateway.local")
            ->setPassword(password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
