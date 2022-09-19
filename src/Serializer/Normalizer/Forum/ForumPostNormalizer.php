<?php

namespace App\Serializer\Normalizer\Forum;

use App\Entity\Forum\ForumPost;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class ForumPostNormalizer implements NormalizerInterface, CacheableSupportsMethodInterface
{
    public function __construct(
        private readonly ObjectNormalizer       $normalizer,
        private readonly HtmlSanitizerInterface $appForumSanitizer
    ) {
    }

    public function normalize(mixed $forumPost, string $format = null, array $context = [])
    {
        /** @var ForumPost $forumPost */
        $forumPostArray = $this->normalizer->normalize($forumPost, $format, $context);
        // we only modify the "content" key in the ForumPost List context
        if (in_array(ForumPost::LIST, $context['groups'])) {
            $forumPostArray['content'] = $this->appForumSanitizer->sanitize(nl2br($forumPost->getContent()));
        }

        return $forumPostArray;
    }

    public function supportsNormalization(mixed $data, string $format = null): bool
    {
        return $data instanceof ForumPost;
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return true;
    }
}