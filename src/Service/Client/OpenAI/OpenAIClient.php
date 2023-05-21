<?php

namespace App\Service\Client\OpenAI;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class OpenAIClient
{
    const CHAT_COMPLETIONS = '/v1/chat/completions';
    const CHAT_COMPLETIONS_DEFAULT_OPTIONS = [
        'json' => [
            'model'       => 'gpt-3.5-turbo',
            'max_tokens'  => 70,
            'temperature' => 0.1,
        ],
    ];

    public function __construct(private readonly HttpClientInterface $openaiClient)
    {
    }

    public function getChatCompletions(array $messages): ResponseInterface
    {
        return $this->openaiClient->request(
            'POST',
            self::CHAT_COMPLETIONS,
            array_merge_recursive(self::CHAT_COMPLETIONS_DEFAULT_OPTIONS, ['json' => ['messages' => $messages,]])
        );
    }
}