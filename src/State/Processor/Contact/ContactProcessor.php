<?php declare(strict_types=1);

namespace App\State\Processor\Contact;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Contact\Contact;
use App\Service\Mail\Brevo\Contact\ContactUsEmail;

/**
 * @implements ProcessorInterface<Contact, Contact>
 */
class ContactProcessor implements ProcessorInterface
{
    public function __construct(private readonly ContactUsEmail $contactUsEmail)
    {
    }

    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): Contact
    {
        $this->contactUsEmail->send($data->name, $data->email, $data->message);

        return $data;
    }
}
