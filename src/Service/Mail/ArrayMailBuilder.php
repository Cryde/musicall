<?php

namespace App\Service\Mail;

class ArrayMailBuilder
{
    /**
     * @var string
     */
    private string $email;
    /**
     * @var string
     */
    private string $name;

    public function __construct(string $email, string $name)
    {
        $this->email = $email;
        $this->name = $name;
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
