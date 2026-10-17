<?php

declare(strict_types=1);

namespace App\ApiResource\User;

use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\User\EmailVerificationCheckProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[Post(
    uriTemplate: '/email/verify/check',
    openapi: new Operation(tags: ['Email Verification']),
    name: 'api_email_verify_check',
    processor: EmailVerificationCheckProcessor::class,
)]
class EmailVerificationCheck
{
    #[Assert\NotBlank(message: 'user.email.required')]
    #[Assert\Email(message: 'user.email.invalid')]
    public string $email;

    #[Assert\NotBlank(message: 'user.email_verification.code_required')]
    #[Assert\Length(exactly: 6, exactMessage: 'user.email_verification.code_length')]
    #[Assert\Regex(pattern: '/^\d{6}$/', message: 'user.email_verification.code_digits_only')]
    public string $code;
}
