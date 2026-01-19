<?php declare(strict_types=1);

namespace App\State\Processor\User\Publication;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\User\Publication\UserPublicationEdit;
use App\Entity\Publication;
use App\Repository\PublicationRepository;
use App\Service\Builder\User\Publication\UserPublicationEditBuilder;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @implements ProcessorInterface<UserPublicationEdit, UserPublicationEdit>
 */
class UserPublicationSubmitProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PublicationRepository $publicationRepository,
        private readonly UserPublicationEditBuilder $builder,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): UserPublicationEdit
    {
        /** @var Publication $publication */
        $publication = $this->publicationRepository->find($uriVariables['id']);

        // Validate required fields for submission
        if (empty($publication->getTitle())) {
            throw new BadRequestHttpException('Le titre ne peut être vide');
        }
        if (empty($publication->getShortDescription())) {
            throw new BadRequestHttpException('La description ne peut être vide');
        }
        if (empty($publication->getContent())) {
            throw new BadRequestHttpException('Le contenu ne peut être vide');
        }
        if (!$publication->getCover()) {
            throw new BadRequestHttpException('Vous devez ajouter une image de couverture');
        }

        $publication->setStatus(Publication::STATUS_PENDING);
        $publication->setPublicationDatetime(new DateTime());
        $publication->setEditionDatetime(new DateTime());

        $this->entityManager->flush();

        return $this->builder->buildFromEntity($publication);
    }
}
