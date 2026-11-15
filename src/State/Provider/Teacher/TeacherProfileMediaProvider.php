<?php

declare(strict_types=1);

namespace App\State\Provider\Teacher;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Teacher\TeacherProfileMedia;
use App\Entity\Service\Service;
use App\Entity\Service\ServiceMedia;
use App\Entity\User;
use App\Enum\Service\ServiceKind;
use App\Repository\Service\ServiceMediaRepository;
use App\Repository\Service\ServiceRepository;
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
        private ServiceMediaRepository $mediaRepository,
        private TeacherProfileMediaResourceBuilder $teacherProfileMediaResourceBuilder,
        private ServiceRepository $serviceRepository,
    ) {
    }

    /**
     * @return TeacherProfileMedia[]|TeacherProfileMedia|null
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|TeacherProfileMedia|null
    {
        /** @var User|null $user */
        $user = $this->security->getUser();
        $profile = $user instanceof User ? $this->serviceRepository->findOneByUserAndKind($user, ServiceKind::Teaching) : null;

        if ($operation instanceof CollectionOperationInterface) {
            return $this->provideCollection($profile);
        }

        return $this->provideItem($uriVariables['id'], $profile);
    }

    /**
     * @return TeacherProfileMedia[]
     */
    private function provideCollection(?Service $profile): array
    {
        if (!$profile instanceof Service) {
            return [];
        }

        $mediaList = $this->mediaRepository->findBy(
            ['service' => $profile],
            ['position' => 'ASC']
        );

        return $this->teacherProfileMediaResourceBuilder->buildList($mediaList);
    }

    private function provideItem(string $id, ?Service $profile): TeacherProfileMedia
    {
        if (!$profile instanceof Service) {
            throw new NotFoundHttpException('profile.teacher.not_found');
        }
        if (!($media = $this->mediaRepository->find($id)) instanceof ServiceMedia) {
            throw new NotFoundHttpException('profile.media.not_found');
        }

        if ($media->service->id !== $profile->id) {
            throw new AccessDeniedHttpException('profile.media.access_forbidden');
        }

        return $this->teacherProfileMediaResourceBuilder->buildFromEntity($media);
    }
}
