<?php declare(strict_types=1);

namespace App\State\Processor\Notification;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use App\Repository\Notification\NotificationRepository;
use DateTimeImmutable;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @implements ProcessorInterface<mixed, null>
 */
readonly class NotificationMarkAllReadProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private NotificationRepository $notificationRepository,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException('Vous n\'êtes pas connecté.');
        }

        $this->notificationRepository->markAllReadForRecipient($user, new DateTimeImmutable());

        return null;
    }
}
