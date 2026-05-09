<?php

declare(strict_types=1);

namespace App\Tests\Factory\User;

use App\Entity\User\EmailVerificationCode;
use DateTimeImmutable;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<EmailVerificationCode>
 */
final class EmailVerificationCodeFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'hashedCode' => 'to_be_set',
            'expirationDatetime' => new DateTimeImmutable('+15 minutes'),
            'creationDatetime' => new DateTimeImmutable('-2 minutes'),
            'attempts' => 0,
        ];
    }

    public static function class(): string
    {
        return EmailVerificationCode::class;
    }
}
