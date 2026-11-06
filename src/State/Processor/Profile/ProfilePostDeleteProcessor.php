<?php

declare(strict_types=1);

namespace App\State\Processor\Profile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Profile\ProfilePostResource;
use App\Entity\Profile\ProfilePost;
use App\Entity\User;
use App\Repository\Profile\ProfilePostRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Removed through the ORM, so VichUploader deletes the stored photos with the row.
 *
 * @implements ProcessorInterface<ProfilePostResource, null>
 */
readonly class ProfilePostDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private ProfilePostRepository $profilePostRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }
        $post = $this->profilePostRepository->findOneOfAuthor((string) $uriVariables['id'], $user);
        if (!$post instanceof ProfilePost) {
            throw new NotFoundHttpException('profile.wall.post_not_found');
        }
        $this->entityManager->remove($post);
        $this->entityManager->flush();

        return null;
    }
}
