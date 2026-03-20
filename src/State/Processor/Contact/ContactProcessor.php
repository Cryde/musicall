<?php declare(strict_types=1);

namespace App\State\Processor\Contact;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Contact\Contact;
use App\Service\Mail\Brevo\Contact\ContactUsEmail;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * @implements ProcessorInterface<Contact, Contact>
 */
class ContactProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly ContactUsEmail $contactUsEmail,
        #[Target('contact_form')]
        private readonly RateLimiterFactoryInterface $contactFormLimiter,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): Contact
    {
        $ip = $this->requestStack->getCurrentRequest()?->getClientIp() ?? 'unknown';
        $limiter = $this->contactFormLimiter->create($ip);
        $limiter->consume()->ensureAccepted();

        $this->contactUsEmail->send($data->name, $data->email, $data->message);

        return $data;
    }
}
