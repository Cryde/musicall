<?php declare(strict_types=1);

namespace App\ApiResource\Publication;

use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\ApiResource\Publication\Publication\Author;
use App\ApiResource\Publication\Publication\Category;
use App\ApiResource\Publication\Publication\Cover;
use App\ApiResource\Publication\Publication\Thread;
use App\ApiResource\Publication\Publication\Type;
use App\State\Provider\Publication\PublicationProvider;
use App\State\Provider\Publication\PublicationSearchProvider;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/publications/{slug}',
            openapi: new Operation(tags: ['Publications']),
            priority: 10,
            name: 'api_publication_get_item',
            provider: PublicationProvider::class
        ),
        new GetCollection(
            uriTemplate: '/publications/search',
            openapi: new Operation(
                tags: ['Publications'],
                parameters: [new Parameter(name: 'term', in: 'query', description: "The query string you want to search", required: true, allowEmptyValue: false, example: "My search title")]
            ),
            paginationEnabled: false,
            priority: 1,
            name: 'api_publication_search',
            provider: PublicationSearchProvider::class
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
)]
class Publication
{
    public string $title;
    #[ApiProperty(genId: false)]
    public Category $category;
    #[ApiProperty(genId: false)]
    public Author $author;
    public string $slug;
    public string $description;
    public string $content;
    public \DateTimeInterface $publicationDatetime;
    #[ApiProperty(genId: false)]
    public Cover $cover;
    #[ApiProperty(genId: false)]
    public Thread $thread;
    #[ApiProperty(genId: false)]
    public Type $type;
    public int $upvotes = 0;
    public int $downvotes = 0;
    public ?int $userVote = null;
}
