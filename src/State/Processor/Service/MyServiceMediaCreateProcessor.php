<?php

declare(strict_types=1);

namespace App\State\Processor\Service;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Service\MyServiceMediaInput;
use App\ApiResource\Teacher\TeacherProfileMedia;
use App\Entity\User;
use App\Security\Service\ServiceOwnerChecker;
use App\Service\Builder\Teacher\TeacherProfileMediaResourceBuilder;
use App\Service\Procedure\Teacher\TeacherProfileMediaCreateProcedure;
use App\Validator\Teacher\TeacherMediaLimit;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProcessorInterface<MyServiceMediaInput, TeacherProfileMedia>
 */
readonly class MyServiceMediaCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private ServiceOwnerChecker $ownerChecker,
        private TeacherProfileMediaCreateProcedure $mediaCreateProcedure,
        private TeacherProfileMediaResourceBuilder $mediaResourceBuilder,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TeacherProfileMedia
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $service = $this->ownerChecker->checkOwner((string) $uriVariables['id'], $user);
        // The teacher profile's limit, for every kind.
        if ($service->media->count() >= TeacherMediaLimit::MAX_MEDIA_PER_PROFILE) {
            throw new UnprocessableEntityHttpException('service.media.limit_reached');
        }

        return $this->mediaResourceBuilder->buildFromEntity($this->mediaCreateProcedure->handle((string) $data->url, $data->title, $service));
    }
}
