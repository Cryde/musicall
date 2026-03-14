<?php declare(strict_types=1);

namespace App\Service\Builder\Forum;

use App\ApiResource\Forum\Data\ForumCategory;
use App\ApiResource\Forum\Forum;
use App\Entity\Forum\Forum as ForumEntity;

readonly class ForumDetailBuilder
{
    public function buildFromEntity(ForumEntity $forum): Forum
    {
        $item = new Forum();
        $item->id = (string) $forum->id;
        $item->title = $forum->title;
        $item->forumCategory = $this->buildForumCategorySimple($forum);

        return $item;
    }

    private function buildForumCategorySimple(ForumEntity $forum): ForumCategory
    {
        $forumCategory = $forum->forumCategory;

        $category = new ForumCategory();
        $category->id = (string) $forumCategory->id;
        $category->title = $forumCategory->title;

        return $category;
    }
}
