<?php

declare(strict_types=1);

namespace App\State\Processor\Service;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Service\ServiceMedia;
use App\Entity\User;
use App\Security\Service\ServiceOwnerChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<mixed, null>
 */
readonly class MyServiceMediaDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private ServiceOwnerChecker $ownerChecker,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $service = $this->ownerChecker->checkOwner((string) $uriVariables['id'], $user);
        $media = $service->media->findFirst(static fn (int $key, ServiceMedia $media): bool => $media->id === (string) $uriVariables['mediaId']);
        if (!$media instanceof ServiceMedia) {
            throw new NotFoundHttpException('profile.media.not_found');
        }

        $service->media->removeElement($media);
        $this->entityManager->flush();

        return null;
    }
}
