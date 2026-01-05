<?php declare(strict_types=1);

namespace App\State\Processor\User;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\User\RequestResetPassword;
use App\Service\User\ResetPassword;

/**
 * @implements ProcessorInterface<RequestResetPassword, void>
 */
readonly class RequestResetPasswordProcessor implements ProcessorInterface
{
    public function __construct(private ResetPassword $resetPassword)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $this->resetPassword->resetPasswordByLogin($data->login);
    }
}
