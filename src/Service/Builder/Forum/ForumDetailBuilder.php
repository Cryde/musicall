<?php

declare(strict_types=1);

namespace App\Service\Builder\Forum;

use App\ApiResource\Forum\Data\ForumCategory;
use App\ApiResource\Forum\Forum;
use App\Entity\Forum\Forum as ForumEntity;

readonly class ForumDetailBuilder
{
    public function buildFromEntity(ForumEntity $forum): Forum
    {
        $item = new Forum();
        $item->id = $forum->getId();
        $item->title = $forum->getTitle();
        $item->forumCategory = $this->buildForumCategorySimple($forum);

        return $item;
    }

    private function buildForumCategorySimple(ForumEntity $forum): ForumCategory
    {
        $category = new ForumCategory();
        $category->id = $forum->getForumCategory()->getId();
        $category->title = $forum->getForumCategory()->getTitle();

        return $category;
    }
}
