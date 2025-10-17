<?php declare(strict_types=1);

namespace App\Service\Builder\Forum;

use App\Entity\Forum\ForumPost;
use App\Entity\Forum\ForumTopic;
use App\Entity\User;

class ForumPostBuilder
{
    public function build(ForumTopic $topic, User $author, string $content): ForumPost
    {
        return (new ForumPost())
            ->setTopic($topic)
            ->setCreator($author)
            ->setContent($content);
    }
}
