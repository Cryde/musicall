<?php

namespace App\Entity\Forum;

use ApiPlatform\Metadata\Post;
use App\State\Processor\Forum\ForumTopicMessageProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[Post(
    uriTemplate: '/forum/topic/post',
    normalizationContext: ['groups' => [ForumTopic::ITEM]],
    security: "is_granted('IS_AUTHENTICATED_REMEMBERED')",
    output: ForumTopic::class,
    name: 'api_forum_topic_post_post',
    processor: ForumTopicMessageProcessor::class
)]
class ForumTopicMessage
{
    #[Assert\NotBlank]
    public Forum $forum;
    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 200)]
    public string $title;
    #[Assert\NotBlank]
    #[Assert\Length(min: ForumPost::MIN_MESSAGE_LENGTH)]
    public string $message;
}
