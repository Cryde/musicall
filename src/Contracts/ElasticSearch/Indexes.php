<?php

namespace App\Contracts\ElasticSearch;
class Indexes
{
    public const INDEX_PUBLICATION = 'publication';
    public const AVAILABLE_INDEXES = [
        self::INDEX_PUBLICATION,
    ];
}