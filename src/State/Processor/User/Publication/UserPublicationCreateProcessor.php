<?php declare(strict_types=1);

namespace App\State\Processor\User\Publication;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\User\Publication\UserPublicationCreate;
use App\ApiResource\User\Publication\UserPublicationEdit;
use App\Entity\Publication;
use App\Entity\User;
use App\Repository\PublicationSubCategoryRepository;
use App\Service\Builder\User\Publication\UserPublicationEditBuilder;
use App\Service\Publication\PublicationSlug;
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
        private readonly PublicationSlug $publicationSlug,
        private readonly UserPublicationEditBuilder $builder,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): UserPublicationEdit
    {
        /** @var UserPublicationCreate $data */
        /** @var User $user */
        $user = $this->security->getUser();

        $category = $this->subCategoryRepository->find($data->categoryId);
        if (!$category) {
            throw new BadRequestHttpException('Category not found');
        }

        $publication = new Publication();
        $publication->setTitle($data->title);
        $publication->setSubCategory($category);
        $publication->setAuthor($user);
        $publication->setSlug($this->publicationSlug->create($data->title));
        $publication->setStatus(Publication::STATUS_DRAFT);
        $publication->setType(Publication::TYPE_TEXT);

        $this->entityManager->persist($publication);
        $this->entityManager->flush();

        return $this->builder->buildFromEntity($publication);
    }
}
