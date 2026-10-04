<?php declare(strict_types=1);

namespace App\State\Processor\User\DeviceToken;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\User\DeviceToken\DeviceTokenResource;
use App\Entity\User;
use App\Enum\User\DevicePlatform;
use App\Repository\User\DeviceTokenRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * @implements ProcessorInterface<DeviceTokenResource, DeviceTokenResource>
 */
readonly class DeviceTokenRegisterProcessor implements ProcessorInterface
{
    public function __construct(
        private DeviceTokenRepository $deviceTokenRepository,
        private Security $security,
        #[Target('device_token_register')]
        private RateLimiterFactoryInterface $deviceTokenRegisterLimiter,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): DeviceTokenResource
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        $this->deviceTokenRegisterLimiter->create($user->getUserIdentifier())->consume()->ensureAccepted();

        $this->deviceTokenRepository->register($user, $data->token, DevicePlatform::from($data->platform));

        return $data;
    }
}
