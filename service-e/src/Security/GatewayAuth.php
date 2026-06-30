<?php

declare(strict_types=1);

namespace App\Security;

/** Имена атрибутов запроса для данных gateway-авторизации. */
final class GatewayAuth
{
    public const string USER_ATTRIBUTE = '_gateway_user';
}
