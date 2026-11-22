<?php declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use Gesdinet\JWTRefreshTokenBundle\Security\Http\Authenticator\Token\PostRefreshTokenAuthenticationToken;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

readonly class AuthenticationSuccessListener
{
    public function __construct(private TokenStorageInterface $tokenStorage)
    {
    }

    public function onAuthenticationSuccessResponse(AuthenticationSuccessEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof User) {
            return;
        }

        // A token refresh is not a login, and stamping it would write fos_user inside the rotation's transaction (#1024).
        // The authenticator manager stores the token before the success handler dispatches this event.
        if ($this->tokenStorage->getToken() instanceof PostRefreshTokenAuthenticationToken) {
            return;
        }

        $user->lastLoginDatetime = new \DateTime();
    }
}
