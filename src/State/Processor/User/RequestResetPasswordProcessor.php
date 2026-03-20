<?php declare(strict_types=1);

namespace App\State\Processor\User;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\User\RequestResetPassword;
use App\Service\User\ResetPassword;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * @implements ProcessorInterface<RequestResetPassword, void>
 */
readonly class RequestResetPasswordProcessor implements ProcessorInterface
{
    public function __construct(
        private ResetPassword $resetPassword,
        #[Target('password_reset')]
        private RateLimiterFactoryInterface $passwordResetLimiter,
        private RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $ip = $this->requestStack->getCurrentRequest()?->getClientIp() ?? 'unknown';
        $this->passwordResetLimiter->create($ip)->consume()->ensureAccepted();

        $this->resetPassword->resetPasswordByLogin($data->login);
    }
}
