<?php declare(strict_types=1);

namespace App\State\Processor\User\Publication;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\User\Publication\UserPublicationCreate;
use App\ApiResource\User\Publication\UserPublicationEdit;
use App\Entity\User;
use App\Repository\PublicationSubCategoryRepository;
use App\Service\Builder\PublicationDirector;
use App\Service\Builder\User\Publication\UserPublicationEditBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @implements ProcessorInterface<UserPublicationCreate, UserPublicationEdit>
 */
class UserPublicationCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly EntityManagerInterface $entityManager,
        private readonly PublicationSubCategoryRepository $subCategoryRepository,
        private readonly PublicationDirector $publicationDirector,
        private readonly UserPublicationEditBuilder $builder,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): UserPublicationEdit
    {
        /** @var UserPublicationCreate $data */
        /** @var User $user */
        $user = $this->security->getUser();

        $category = $this->subCategoryRepository->find($data->categoryId);
        if (!$category instanceof \App\Entity\PublicationSubCategory) {
            throw new BadRequestHttpException('publication.user.category_not_found');
        }

        $publication = $this->publicationDirector->buildTextDraft($data->title, $category, $user);

        $this->entityManager->persist($publication);
        $this->entityManager->flush();

        return $this->builder->buildFromEntity($publication);
    }
}
