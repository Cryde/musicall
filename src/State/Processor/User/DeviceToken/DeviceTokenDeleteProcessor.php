<?php declare(strict_types=1);

namespace App\State\Processor\User\DeviceToken;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use App\Repository\User\DeviceTokenRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * An unknown token and another user's token answer the same 404, so the endpoint never tells
 * whether a token exists.
 *
 * @implements ProcessorInterface<mixed, void>
 */
readonly class DeviceTokenDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private DeviceTokenRepository $deviceTokenRepository,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        if ($this->deviceTokenRepository->deleteOneForUser($user, (string) $uriVariables['token']) === 0) {
            throw new NotFoundHttpException('Appareil introuvable');
        }
    }
}
