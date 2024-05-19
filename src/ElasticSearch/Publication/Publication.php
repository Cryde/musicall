<?php

namespace App\ElasticSearch\Publication;

use App\Enum\Publication\PublicationCategoryType;

class Publication
{
    public string $id;
    public string $title;
    public PublicationCategoryType $publicationType;
    public string $slug;
    public bool $isVideo;
    public string $content;
    public string $shortDescription;
    public \DateTimeInterface $publicationDatetime;
    public Media $media;
    public Author $author;
    public Category $category;
}