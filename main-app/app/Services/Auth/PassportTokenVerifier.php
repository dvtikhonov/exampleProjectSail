<?php

namespace App\Services\Auth;

use Carbon\Carbon;
use Laravel\Passport\Token;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;

class PassportTokenVerifier
{
    public function resolveUserId(?string $bearerToken): ?int
    {
        if ($bearerToken === null || $bearerToken === '') {
            return null;
        }

        try {
            $parser = new Parser(new JoseEncoder);
            $jwt = $parser->parse($bearerToken);
            $claims = $jwt->claims();

            if (! $claims->has('jti')) {
                return null;
            }

            $token = Token::find($claims->get('jti'));

            if (! $token || $token->revoked) {
                return null;
            }

            if ($token->expires_at && Carbon::parse($token->expires_at)->isPast()) {
                return null;
            }

            return (int) $token->user_id;
        } catch (\Throwable) {
            return null;
        }
    }
}
