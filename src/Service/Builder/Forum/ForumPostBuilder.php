<?php declare(strict_types=1);

namespace App\Service\Builder\Forum;

use App\Entity\Forum\ForumPost;
use App\Entity\Forum\ForumTopic;
use App\Entity\User;

class ForumPostBuilder
{
    public function build(ForumTopic $topic, User $author, string $content): ForumPost
    {
        $post = new ForumPost();
        $post->topic = $topic;
        $post->creator = $author;
        $post->content = $content;

        return $post;
    }
}
