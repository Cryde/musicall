<?php

declare(strict_types=1);

namespace App\State\Processor\Service;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Service\MyServiceAudioInput;
use App\ApiResource\Service\MyServiceAudio;
use App\Entity\User;
use App\Enum\Service\BeforeAfterSide;
use App\Enum\Service\ServicePart;
use App\Security\Service\ServiceOwnerChecker;
use App\Service\Builder\Service\ServiceViewBuilder;
use App\Service\Procedure\Service\ServiceAudioProcedure;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * @implements ProcessorInterface<MyServiceAudioInput, MyServiceAudio>
 */
readonly class MyServiceAudioUploadProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private ServiceOwnerChecker $ownerChecker,
        private ServiceAudioProcedure $procedure,
        private ServiceViewBuilder $viewBuilder,
        #[Target('service_audio_upload')]
        private RateLimiterFactoryInterface $uploadLimiter,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MyServiceAudio
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $service = $this->ownerChecker->checkOwnerWithPart((string) $uriVariables['id'], $user, ServicePart::BeforeAfter);
        $this->uploadLimiter->create((string) $user->id)->consume()->ensureAccepted();
        $audio = $this->procedure->store($service, BeforeAfterSide::from((string) $uriVariables['side']), $data);

        $resource = $this->viewBuilder->audio($audio, new MyServiceAudio());
        $resource->id = (string) $service->id;

        return $resource;
    }
}
