<?php

declare(strict_types=1);

namespace App\Http\Resolvers;

use App\Contracts\Max\AuthenticatedMaxUserResolverInterface;
use App\Contracts\Shared\CurrentHttpRequestInterface;
use App\DTO\Food\Shared\MaxUserIdentity;
use App\DTO\Max\MaxUserRecord;
use App\Http\Mappers\MaxUserIdentityMapper;
use App\Models\Max\MaxUser;
use App\Repositories\Max\MaxUserMapper;
use RuntimeException;

/**
 * HTTP-резолвер аутентифицированного MaxUser и его доменной проекции.
 *
 * Request не инжектится в конструктор: Route кэширует контроллер (и его
 * зависимости) между HTTP-вызовами в одном процессе. User читается через
 * {@see CurrentHttpRequestInterface} на каждый вызов.
 */
final class AuthenticatedMaxUserResolver implements AuthenticatedMaxUserResolverInterface
{
    public function __construct(
        private readonly CurrentHttpRequestInterface $currentHttpRequest,
        private readonly MaxUserIdentityMapper $identityMapper,
        private readonly MaxUserMapper $maxUserMapper,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function identity(): MaxUserIdentity
    {
        return $this->identityMapper->fromModel($this->authenticatedMaxUser());
    }

    /**
     * {@inheritDoc}
     */
    public function record(): MaxUserRecord
    {
        return $this->maxUserMapper->toRecord($this->authenticatedMaxUser());
    }

    /**
     * Возвращает Eloquent MaxUser из текущего HTTP-запроса.
     *
     * @throws RuntimeException если пользователь не аутентифицирован как MaxUser
     */
    private function authenticatedMaxUser(): MaxUser
    {
        $user = $this->currentHttpRequest->user();

        if (! $user instanceof MaxUser) {
            throw new RuntimeException('Authenticated MaxUser is required.');
        }

        return $user;
    }
}
