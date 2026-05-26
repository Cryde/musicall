<?php declare(strict_types=1);

namespace App\State\Processor\Notification;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Notification\Notification;
use App\Entity\User;
use App\Repository\Notification\NotificationRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @implements ProcessorInterface<mixed, null>
 */
readonly class NotificationReadProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private NotificationRepository $notificationRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException('Vous n\'êtes pas connecté.');
        }

        $notification = $this->notificationRepository->findOneByIdAndRecipient((string) $uriVariables['id'], $user);
        if (!$notification instanceof Notification) {
            throw new NotFoundHttpException('Notification introuvable');
        }

        if ($notification->readDatetime === null) {
            $notification->readDatetime = new DateTimeImmutable();
            $this->entityManager->flush();
        }

        return null;
    }
}
