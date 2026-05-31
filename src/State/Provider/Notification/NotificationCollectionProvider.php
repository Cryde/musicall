<?php declare(strict_types=1);

namespace App\State\Provider\Notification;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Notification\UserNotification;
use App\Entity\User;
use App\Repository\Notification\NotificationRepository;
use App\Service\Builder\Notification\NotificationBuilder;
use App\Service\Notification\NotificationFeedEnricher;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @implements ProviderInterface<UserNotification>
 */
readonly class NotificationCollectionProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private NotificationRepository $notificationRepository,
        private NotificationBuilder $notificationBuilder,
        private Pagination $pagination,
        private NotificationFeedEnricher $feedEnricher,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TraversablePaginator
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException('Vous n\'êtes pas connecté.');
        }

        $page = $this->pagination->getPage($context);
        $limit = $this->pagination->getLimit($operation, $context);
        $offset = $this->pagination->getOffset($operation, $context);

        $entities = $this->notificationRepository->findForRecipient($user, $limit, $offset);
        $totalItems = $this->notificationRepository->countForRecipient($user);
        $dtos = $this->notificationBuilder->buildFromList($entities);
        $this->feedEnricher->enrich($dtos);

        return new TraversablePaginator(new \ArrayIterator($dtos), $page, $limit, $totalItems);
    }
}
