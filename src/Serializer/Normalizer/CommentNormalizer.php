<?php

namespace App\Serializer\Normalizer;

use App\Entity\Comment\Comment;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class CommentNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const ALREADY_CALLED = 'COMMENT_NORMALIZER_ALREADY_CALLED';

    public function __construct(private readonly HtmlSanitizerInterface $appOnlybrSanitizer)
    {
    }

    public function normalize(mixed $comment, string $format = null, array $context = []): float|int|bool|\ArrayObject|array|string|null
    {
        $context[self::ALREADY_CALLED] = true;
        /** @var Comment $comment */
        $arrayComment = $this->normalizer->normalize($comment, $format, $context);
        $arrayComment['content'] = $this->appOnlybrSanitizer->sanitize(nl2br($comment->getContent()));

        return $arrayComment;
    }

    public function supportsNormalization(mixed $data, string $format = null, array $context = []): bool
    {
        if (isset($context[self::ALREADY_CALLED])) {
            return false;
        }

        return $data instanceof Comment;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [Comment::class => false];
    }
}