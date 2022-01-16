<?php

namespace App\Serializer\Publication;

use App\Entity\PublicationSubCategory;

class SubCategoryArraySerializer
{
    public function toArray(PublicationSubCategory $category): array
    {
        return [
            'id'    => $category->getId(),
            'slug'  => $category->getSlug(),
            'title' => $category->getTitle(),
        ];
    }
}
