<?php

namespace App\Serializer;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class ExceptionSerializer implements NormalizerInterface
{
    public function normalize($exception, string $format = null, array $context = [])
    {
        return [
            'title' => $exception->getStatusText(),
            'message' => $exception->getMessage(),
            'status' => $exception->getStatusCode(),
        ];
    }

    public function supportsNormalization($data, string $format = null)
    {
        return 'json' === $format && $data instanceof FlattenException;
    }
}
