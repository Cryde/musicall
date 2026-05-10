<?php declare(strict_types=1);

namespace App\State\Provider\Message;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Message\MessageThreadMetaResource;
use App\Entity\User;
use App\Repository\Message\MessageThreadMetaRepository;
use App\Service\Builder\Message\MessageThreadMetaBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @implements ProviderInterface<MessageThreadMetaResource>
 */
readonly class MessageThreadMetaCollectionProvider implements ProviderInterface
{
    public function __construct(
        private MessageThreadMetaRepository $messageThreadMetaRepository,
        private MessageThreadMetaBuilder    $messageThreadMetaBuilder,
        private Security                    $security,
    ) {
    }

    /**
     * @return MessageThreadMetaResource[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        if (!$this->security->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            throw new AccessDeniedException('Vous n\'êtes pas connecté.');
        }
        /** @var User $user */
        $user = $this->security->getUser();

        $entities = $this->messageThreadMetaRepository->findByUserAndNotDeleted($user);

        return $this->messageThreadMetaBuilder->buildList($entities);
    }
}
