<?php declare(strict_types=1);

namespace App\State\Provider\Notification;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Notification\UserNotification;
use App\Entity\Notification\Notification;
use App\Entity\User;
use App\Repository\Notification\NotificationRepository;
use App\Service\Builder\Notification\NotificationBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @implements ProviderInterface<UserNotification>
 */
readonly class NotificationItemProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private NotificationRepository $notificationRepository,
        private NotificationBuilder $notificationBuilder,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): UserNotification
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException('Vous n\'êtes pas connecté.');
        }

        $notification = $this->notificationRepository->findOneByIdAndRecipient((string) $uriVariables['id'], $user);
        if (!$notification instanceof Notification) {
            throw new NotFoundHttpException('Notification introuvable');
        }

        return $this->notificationBuilder->buildFromEntity($notification);
    }
}
