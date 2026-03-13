<?php

declare(strict_types=1);

namespace App\ApiResource\User;

use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\User\EmailVerificationSendProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[Post(
    uriTemplate: '/email/verify/send',
    openapi: new Operation(tags: ['Email Verification']),
    name: 'api_email_verify_send',
    processor: EmailVerificationSendProcessor::class,
)]
class EmailVerificationSend
{
    #[Assert\NotBlank(message: 'Veuillez saisir un email')]
    #[Assert\Email(message: 'Email invalide')]
    public string $email;
}
