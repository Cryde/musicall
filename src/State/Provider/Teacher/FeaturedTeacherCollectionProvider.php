<?php

declare(strict_types=1);

namespace App\State\Provider\Teacher;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Teacher\Public\FeaturedTeacher;
use App\Repository\Teacher\TeacherProfileRepository;
use App\Service\Builder\Teacher\FeaturedTeacherBuilder;

/**
 * @implements ProviderInterface<object>
 */
readonly class FeaturedTeacherCollectionProvider implements ProviderInterface
{
    public function __construct(
        private TeacherProfileRepository $teacherProfileRepository,
        private FeaturedTeacherBuilder $featuredTeacherBuilder,
    ) {
    }

    /**
     * @return FeaturedTeacher[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $profiles = $this->teacherProfileRepository->findAllWithInstruments();

        return $this->featuredTeacherBuilder->buildList($profiles);
    }
}
