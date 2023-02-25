<?php

namespace App\Serializer\Normalizer\Forum;

use App\Entity\Forum\ForumPost;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

class ForumPostNormalizer implements NormalizerInterface, SerializerAwareInterface
{
    public function __construct(
        private readonly NormalizerInterface    $decorated,
        private readonly HtmlSanitizerInterface $appForumSanitizer
    ) {
    }

    public function normalize(mixed $forumPost, string $format = null, array $context = [])
    {
        /** @var ForumPost $forumPost */
        $forumPostArray = $this->decorated->normalize($forumPost, $format, $context);
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

    public function setSerializer(SerializerInterface $serializer)
    {
        if ($this->decorated instanceof SerializerAwareInterface) {
            $this->decorated->setSerializer($serializer);
        }
    }
}