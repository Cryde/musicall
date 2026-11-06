<?php

declare(strict_types=1);

namespace App\State\Processor\Profile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Profile\ProfilePostResource;
use App\Entity\Profile\ProfilePost;
use App\Entity\User;
use App\Repository\Profile\ProfilePostRepository;
use App\Service\Builder\Profile\ProfilePostResourceBuilder;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<ProfilePostResource, ProfilePostResource>
 */
readonly class ProfilePostEditProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private ProfilePostRepository $profilePostRepository,
        private EntityManagerInterface $entityManager,
        private ProfilePostResourceBuilder $postBuilder,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ProfilePostResource
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }
        $post = $this->profilePostRepository->findOneOfAuthor((string) $uriVariables['id'], $user);
        if (!$post instanceof ProfilePost) {
            throw new NotFoundHttpException('profile.wall.post_not_found');
        }

        $content = trim((string) $data->content);
        $content = $content === '' ? null : $content;
        // An identical text is not an edit: « modifié » would say something that did not happen.
        if ($content !== $post->content) {
            $post->content = $content;
            $post->updateDatetime = new DateTimeImmutable();
        }

        $this->entityManager->wrapInTransaction(function () use ($post, $data, $user): void {
            if ($data->isPinned && !$post->isPinned) {
                $this->profilePostRepository->unpinAllOf($user);
            }
            $post->isPinned = $data->isPinned;
            $this->entityManager->flush();
        });

        return $this->postBuilder->build($post, $user);
    }
}
