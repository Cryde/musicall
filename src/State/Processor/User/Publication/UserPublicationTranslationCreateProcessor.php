<?php declare(strict_types=1);

namespace App\State\Processor\User\Publication;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\User\Publication\UserPublicationEdit;
use App\ApiResource\User\Publication\UserPublicationTranslationCreate;
use App\Entity\Publication;
use App\Repository\PublicationRepository;
use App\Service\Builder\PublicationDirector;
use App\Service\Builder\User\Publication\UserPublicationEditBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Starts from the title and the category only: the content is the author's to write, and copying it would point
 * the translation at images that are deleted with the original.
 *
 * @implements ProcessorInterface<UserPublicationTranslationCreate, UserPublicationEdit>
 */
readonly class UserPublicationTranslationCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PublicationRepository $publicationRepository,
        private PublicationDirector $publicationDirector,
        private UserPublicationEditBuilder $builder,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): UserPublicationEdit
    {
        /** @var UserPublicationTranslationCreate $data */
        // The id the provider checked the ownership of, never one read from the body.
        $original = $this->publicationRepository->find($uriVariables['id']);
        if (!$original instanceof Publication) {
            throw new NotFoundHttpException('publication.user.not_found');
        }

        $translation = $this->publicationDirector->buildTextDraft($data->title, $original->subCategory, $original->author);
        $translation->language = $data->language;
        $translation->joinTranslationGroupOf($original);

        $this->entityManager->persist($translation);
        $this->entityManager->flush();

        return $this->builder->buildFromEntity($translation);
    }
}
