<?php declare(strict_types=1);

namespace App\Service\Builder\Forum;

use App\Entity\Forum\Forum;
use App\Entity\Forum\ForumTopic;
use App\Entity\User;

class ForumTopicBuilder
{
    public function build(Forum $forum, User $author, string $title): ForumTopic
    {
        return (new ForumTopic())
            ->setForum($forum)
            ->setAuthor($author)
            ->setTitle($title);
    }
}
