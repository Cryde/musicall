<?php

namespace App\Serializer;

use App\Entity\PublicationSubCategory;

class PublicationCategoryArraySerializer
{
    /**
     * @param array|PublicationSubCategory[] $categories
     *
     * @return array
     */
    public function listToArray(array $categories): array
    {
        $result = [];
        foreach ($categories as $category) {
            $result[] = $this->toArray($category);
        }

        return $result;
    }

    /**
     * @param PublicationSubCategory $category
     *
     * @return array
     */
    public function toArray(PublicationSubCategory $category): array
    {
        return [
            'id'    => $category->getId(),
            'title' => $category->getTitle(),
            'slug'  => $category->getSlug(),
        ];
    }
}
