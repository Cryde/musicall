<?php declare(strict_types=1);

namespace App\State\Provider\User\Publication;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\User\Publication\UserPublicationTranslationCreate;
use App\Entity\Publication;
use App\Entity\User;
use App\Repository\PublicationRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Only the author translates a publication, whatever its status: the translation is a draft of its own.
 *
 * @implements ProviderInterface<UserPublicationTranslationCreate>
 */
readonly class UserPublicationTranslationCreateProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private PublicationRepository $publicationRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): UserPublicationTranslationCreate
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException('publication.not_authenticated');
        }

        $publication = $this->publicationRepository->find($uriVariables['id']);
        if (!$publication instanceof Publication) {
            throw new NotFoundHttpException('publication.user.not_found');
        }

        if ($publication->author->id !== $user->id) {
            throw new AccessDeniedHttpException('publication.user.not_owner');
        }

        if ($publication->type === Publication::TYPE_VIDEO) {
            throw new UnprocessableEntityHttpException('publication.user.translation.video');
        }

        $translationCreate = new UserPublicationTranslationCreate();
        $translationCreate->id = (int) $publication->id;
        $translationCreate->takenLanguages = array_map(
            static fn (Publication $member): string => (string) $member->language,
            [$publication, ...$this->publicationRepository->findTranslationSiblings($publication)],
        );

        return $translationCreate;
    }
}
