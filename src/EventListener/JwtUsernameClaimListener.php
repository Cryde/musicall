<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * The JWT names its user by id (#1134), so lexik no longer writes the username. It goes back in as
 * display data only, because the web reads it from the token: nothing authenticates on it.
 */
#[AsEventListener(event: Events::JWT_CREATED)]
final class JwtUsernameClaimListener
{
    public function __invoke(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User) {
            return;
        }

        $event->setData([...$event->getData(), 'username' => $user->username]);
    }
}
