<?php

namespace App\Processor\Contact;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Contact\Contact;
use App\Service\Mail\Brevo\Contact\ContactUsEmail;

class ContactProcessor implements ProcessorInterface
{
    public function __construct(private readonly ContactUsEmail $contactUsEmail)
    {
    }

    /** @param Contact $data */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $this->contactUsEmail->send($data->name, $data->email, $data->message);

        return $data;
    }
}