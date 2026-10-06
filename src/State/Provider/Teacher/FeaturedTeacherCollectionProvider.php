<?php

declare(strict_types=1);

namespace App\State\Provider\Teacher;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Teacher\Public\FeaturedTeacher;
use App\Entity\User;
use App\Repository\Teacher\TeacherProfileRepository;
use App\Service\Builder\Teacher\FeaturedTeacherBuilder;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<object>
 */
readonly class FeaturedTeacherCollectionProvider implements ProviderInterface
{
    public function __construct(
        private TeacherProfileRepository $teacherProfileRepository,
        private FeaturedTeacherBuilder $featuredTeacherBuilder,
        private Security $security,
    ) {
    }

    /**
     * @return FeaturedTeacher[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $viewer = $this->security->getUser();
        $profiles = $this->teacherProfileRepository->findAllWithInstruments($viewer instanceof User ? $viewer : null);

        return $this->featuredTeacherBuilder->buildList($profiles);
    }
}
