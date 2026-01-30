<?php

declare(strict_types=1);

namespace App\State\Processor\Teacher;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Teacher\Profile\Media;
use App\ApiResource\Teacher\TeacherProfileMedia;
use App\Entity\User;
use App\Service\Builder\Teacher\TeacherProfileMediaResourceBuilder;
use App\Service\Procedure\Teacher\TeacherProfileMediaCreateProcedure;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<Media, TeacherProfileMedia>
 */
readonly class TeacherProfileMediaCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private TeacherProfileMediaCreateProcedure $mediaCreateProcedure,
        private TeacherProfileMediaResourceBuilder $teacherProfileMediaResourceBuilder,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TeacherProfileMedia
    {
        /** @var Media $data */
        /** @var User $user */
        $user = $this->security->getUser();
        if (!$profile = $user->getTeacherProfile()) {
            throw new NotFoundHttpException('Profil professeur non trouvé');
        }

        $media = $this->mediaCreateProcedure->handle($data, $profile);

        return $this->teacherProfileMediaResourceBuilder->buildFromEntity($media);
    }
}
