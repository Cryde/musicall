<?php declare(strict_types=1);

namespace App\State\Processor\User\Publication;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\User\Publication\UserPublicationEdit;
use App\Entity\Publication;
use App\Repository\PublicationRepository;
use App\Repository\PublicationSubCategoryRepository;
use App\Service\Builder\User\Publication\UserPublicationEditBuilder;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @implements ProcessorInterface<UserPublicationEdit, UserPublicationEdit>
 */
class UserPublicationEditProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PublicationRepository $publicationRepository,
        private readonly PublicationSubCategoryRepository $subCategoryRepository,
        private readonly UserPublicationEditBuilder $builder,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): UserPublicationEdit
    {
        /** @var UserPublicationEdit $data */
        /** @var Publication $publication */
        $publication = $this->publicationRepository->find($uriVariables['id']);

        if (isset($data->title)) {
            $publication->setTitle($data->title);
        }
        if (isset($data->shortDescription)) {
            $publication->setShortDescription($data->shortDescription);
        }
        if (isset($data->content)) {
            $publication->setContent($data->content);
        }
        if (isset($data->categoryId)) {
            $category = $this->subCategoryRepository->find($data->categoryId);
            if (!$category) {
                throw new BadRequestHttpException('Category not found');
            }
            $publication->setSubCategory($category);
        }

        $publication->setEditionDatetime(new DateTime());

        $this->entityManager->flush();

        return $this->builder->buildFromEntity($publication);
    }
}
