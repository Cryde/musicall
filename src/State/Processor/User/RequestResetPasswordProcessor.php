<?php

namespace App\State\Processor\User;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\User\RequestResetPassword;
use App\Service\User\ResetPassword;

class RequestResetPasswordProcessor implements ProcessorInterface
{
    public function __construct(private ResetPassword  $resetPassword)
    {
    }

    /**
     * @param RequestResetPassword $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $this->resetPassword->resetPasswordByLogin($data->login);
    }
}