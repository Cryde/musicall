<?php

namespace App\Serializer;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class ExceptionSerializer implements NormalizerInterface
{
    public function normalize($exception, string $format = null, array $context = []): float|int|bool|\ArrayObject|array|string|null
    {
        return [
            'title' => $exception->getStatusText(),
            'message' => $exception->getMessage(),
            'status' => $exception->getStatusCode(),
        ];
    }

    public function supportsNormalization($data, string $format = null, array $context = []): bool
    {
        return 'json' === $format && $data instanceof FlattenException;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [FlattenException::class => false];
    }
}
