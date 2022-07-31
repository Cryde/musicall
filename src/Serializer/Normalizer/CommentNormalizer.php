<?php

namespace App\Serializer\Normalizer;

use App\Entity\Comment\Comment;
use HtmlSanitizer\SanitizerInterface;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class CommentNormalizer implements NormalizerInterface, CacheableSupportsMethodInterface
{
    public function __construct(
        private readonly ObjectNormalizer   $normalizer,
        private readonly SanitizerInterface $sanitizer
    ) {
    }

    public function normalize(mixed $comment, string $format = null, array $context = [])
    {
        /** @var Comment $comment */
        $arrayComment = $this->normalizer->normalize($comment, $format, $context);
        $arrayComment['content'] = $this->sanitizer->sanitize(nl2br($comment->getContent()));

        return $arrayComment;
    }

    public function supportsNormalization(mixed $data, string $format = null): bool
    {
        return $data instanceof Comment;
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return true;
    }
}