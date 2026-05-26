<?php declare(strict_types=1);

namespace App\State\Provider\Notification;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Notification\UserNotificationCount;
use App\Entity\User;
use App\Repository\Notification\NotificationRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @implements ProviderInterface<UserNotificationCount>
 */
readonly class NotificationCountProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private NotificationRepository $notificationRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): UserNotificationCount
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException('Vous n\'êtes pas connecté.');
        }

        $count = new UserNotificationCount();
        $count->unread = $this->notificationRepository->countUnread($user);

        return $count;
    }
}
