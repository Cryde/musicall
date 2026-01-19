<?php

declare(strict_types=1);

namespace App\Tests\Factory\User;

use App\Entity\User\UserEmailLog;
use App\Enum\User\UserEmailType;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<UserEmailLog>
 */
final class UserEmailLogFactory extends PersistentProxyObjectFactory
{
    protected function defaults(): array
    {
        return [
            'user' => UserFactory::new(),
            'emailType' => self::faker()->randomElement(UserEmailType::cases()),
            'referenceId' => null,
            'metadata' => null,
        ];
    }

    public function emailConfirmationReminder(int $reminderNumber = 1): static
    {
        return $this->with([
            'emailType' => UserEmailType::EMAIL_CONFIRMATION_REMINDER,
            'metadata' => ['reminder_number' => $reminderNumber],
        ]);
    }

    public function inactivityReminder(): static
    {
        return $this->with([
            'emailType' => UserEmailType::INACTIVITY_REMINDER,
        ]);
    }

    public function welcome(): static
    {
        return $this->with([
            'emailType' => UserEmailType::WELCOME,
        ]);
    }

    public static function class(): string
    {
        return UserEmailLog::class;
    }
}
