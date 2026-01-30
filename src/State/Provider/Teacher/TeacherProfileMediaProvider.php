<?php

declare(strict_types=1);

namespace App\State\Provider\Teacher;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Teacher\TeacherProfileMedia;
use App\Entity\Teacher\TeacherProfile;
use App\Entity\User;
use App\Repository\Teacher\TeacherProfileMediaRepository;
use App\Service\Builder\Teacher\TeacherProfileMediaResourceBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<TeacherProfileMedia>
 */
readonly class TeacherProfileMediaProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private TeacherProfileMediaRepository $mediaRepository,
        private TeacherProfileMediaResourceBuilder $teacherProfileMediaResourceBuilder,
    ) {
    }

    /**
     * @return TeacherProfileMedia[]|TeacherProfileMedia|null
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|TeacherProfileMedia|null
    {
        /** @var User|null $user */
        $user = $this->security->getUser();
        $profile = $user?->getTeacherProfile();

        if ($operation instanceof CollectionOperationInterface) {
            return $this->provideCollection($profile);
        }

        return $this->provideItem($uriVariables['id'], $profile);
    }

    /**
     * @return TeacherProfileMedia[]
     */
    private function provideCollection(?TeacherProfile $profile): array
    {
        if (!$profile) {
            return [];
        }

        $mediaList = $this->mediaRepository->findBy(
            ['teacherProfile' => $profile],
            ['position' => 'ASC']
        );

        return $this->teacherProfileMediaResourceBuilder->buildList($mediaList);
    }

    private function provideItem(string $id, ?TeacherProfile $profile): TeacherProfileMedia
    {
        if (!$profile) {
            throw new NotFoundHttpException('Profil professeur non trouvé');
        }
        if (!$media = $this->mediaRepository->find($id)) {
            throw new NotFoundHttpException('Média non trouvé');
        }

        if ($media->getTeacherProfile()->getId() !== $profile->getId()) {
            throw new AccessDeniedHttpException('Vous ne pouvez pas accéder à ce média');
        }

        return $this->teacherProfileMediaResourceBuilder->buildFromEntity($media);
    }
}
