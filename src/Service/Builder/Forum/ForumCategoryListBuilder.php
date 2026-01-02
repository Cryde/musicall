<?php

declare(strict_types=1);

namespace App\Service\Builder\Forum;

use App\ApiResource\Forum\ForumCategoryItem;
use App\ApiResource\Forum\ForumItem;
use App\Entity\Forum\Forum;
use App\Entity\Forum\ForumCategory;

readonly class ForumCategoryListBuilder
{
    /**
     * @param ForumCategory[] $categories
     *
     * @return ForumCategoryItem[]
     */
    public function buildFromEntities(array $categories): array
    {
        return array_map(
            fn (ForumCategory $category): ForumCategoryItem => $this->buildCategoryItem($category),
            $categories
        );
    }

    private function buildCategoryItem(ForumCategory $category): ForumCategoryItem
    {
        $item = new ForumCategoryItem();
        $item->id = $category->getId();
        $item->title = $category->getTitle();
        $item->forums = $this->buildForumItems($category->getForums()->toArray());

        return $item;
    }

    /**
     * @param Forum[] $forums
     *
     * @return ForumItem[]
     */
    private function buildForumItems(array $forums): array
    {
        // Sort forums by position
        usort($forums, fn (Forum $a, Forum $b): int => $a->getPosition() <=> $b->getPosition());

        return array_map(
            fn (Forum $forum): ForumItem => $this->buildForumItem($forum),
            $forums
        );
    }

    private function buildForumItem(Forum $forum): ForumItem
    {
        $item = new ForumItem();
        $item->id = $forum->getId();
        $item->title = $forum->getTitle();
        $item->slug = $forum->getSlug();
        $item->description = $forum->getDescription();

        return $item;
    }
}
