<?php

declare(strict_types=1);

namespace App\State\Processor\Service\Work;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Service\Work\MyServiceWorkImage;
use App\ApiResource\Service\Work\MyServiceWorkImageInput;
use App\Entity\Service\ServiceWorkImage;
use App\Entity\User;
use App\Security\Service\ServiceOwnerChecker;
use App\Service\Builder\Service\MyServiceWorkBuilder;
use App\Service\Procedure\Service\ServiceWorkProcedure;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * @implements ProcessorInterface<MyServiceWorkImageInput, MyServiceWorkImage>
 */
readonly class MyServiceWorkImageUploadProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private ServiceOwnerChecker $ownerChecker,
        private ServiceWorkProcedure $procedure,
        private MyServiceWorkBuilder $builder,
        #[Target('service_work_image_upload')]
        private RateLimiterFactoryInterface $uploadLimiter,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MyServiceWorkImage
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $work = $this->ownerChecker->checkOwnerOfWork((string) $uriVariables['serviceId'], (string) $uriVariables['workId'], $user);
        if ($work->images->count() >= ServiceWorkImage::MAX_PER_WORK) {
            throw new UnprocessableEntityHttpException('service.work.image_limit_reached');
        }
        $this->uploadLimiter->create((string) $user->id)->consume()->ensureAccepted();

        /** @var File $upload validated */
        $upload = $data->file;

        return $this->builder->image($this->procedure->addImage($work, $upload));
    }
}
