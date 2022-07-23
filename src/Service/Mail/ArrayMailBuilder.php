<?php

namespace App\Service\Mail;

class ArrayMailBuilder
{
    public function __construct(private readonly string $email, private readonly string $name)
    {
    }

    public function build(string $recipientEmail, int $templateId, string $subject, array $variables): array
    {
        return [
            'Messages' => [
                [
                    'From'             => [
                        'Email' => $this->email,
                        'Name'  => $this->name,
                    ],
                    'To'               => [
                        [
                            'Email' => $recipientEmail,
                        ],
                    ],
                    'TemplateID'       => $templateId,
                    'TemplateLanguage' => true,
                    'Subject'          => $subject,
                    'Variables'        => $variables,
                ],
            ],
        ];
    }
}
