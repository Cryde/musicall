<?php

namespace App\Serializer\Normalizer\Forum;

use App\Entity\Forum\ForumPost;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class ForumPostNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const ALREADY_CALLED = 'FORUM_POST_NORMALIZER_ALREADY_CALLED';

    public function __construct(
        private readonly HtmlSanitizerInterface $appForumSanitizer
    ) {
    }

    public function normalize(mixed $forumPost, string $format = null, array $context = []): float|int|bool|\ArrayObject|array|string|null
    {
        $context[self::ALREADY_CALLED] = true;
        /** @var ForumPost $forumPost */
        $forumPostArray = $this->normalizer->normalize($forumPost, $format, $context);
        // we only modify the "content" key in the ForumPost List context
        if (in_array(ForumPost::LIST, $context['groups'])) {
            $forumPostArray['content'] = $this->appForumSanitizer->sanitize(nl2br($forumPost->getContent()));
        }

        return $forumPostArray;
    }

    public function supportsNormalization(mixed $data, string $format = null, array $context = []): bool
    {
        if (isset($context[self::ALREADY_CALLED])) {
            return false;
        }

        return $data instanceof ForumPost;
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return false;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [ForumPost::class => true];
    }
}