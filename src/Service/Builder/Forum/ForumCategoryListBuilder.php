<?php

declare(strict_types=1);

namespace App\Service\Builder\Forum;

use App\ApiResource\Forum\Data\Forum;
use App\ApiResource\Forum\ForumCategory;
use App\Entity\Forum\Forum as ForumEntity;
use App\Entity\Forum\ForumCategory as ForumCategoryEntity;

readonly class ForumCategoryListBuilder
{
    /**
     * @param ForumCategoryEntity[] $categories
     *
     * @return ForumCategory[]
     */
    public function buildFromEntities(array $categories): array
    {
        return array_map(
            fn (ForumCategoryEntity $category): ForumCategory => $this->buildCategoryItem($category),
            $categories
        );
    }

    private function buildCategoryItem(ForumCategoryEntity $category): ForumCategory
    {
        $item = new ForumCategory();
        $item->id = (string) $category->getId();
        $item->title = (string) $category->getTitle();
        $item->forums = $this->buildForumItems($category->getForums()->toArray());

        return $item;
    }

    /**
     * @param ForumEntity[] $forums
     *
     * @return Forum[]
     */
    private function buildForumItems(array $forums): array
    {
        // Sort forums by position
        usort($forums, fn (ForumEntity $a, ForumEntity $b): int => $a->getPosition() <=> $b->getPosition());

        return array_map(
            fn (ForumEntity $forum): Forum => $this->buildForumItem($forum),
            $forums
        );
    }

    private function buildForumItem(ForumEntity $forum): Forum
    {
        $item = new Forum();
        $item->id = (string) $forum->getId();
        $item->title = (string) $forum->getTitle();
        $item->slug = (string) $forum->getSlug();
        $item->description = (string) $forum->getDescription();

        return $item;
    }
}
