<?php declare(strict_types=1);

namespace App\ApiResource\Forum;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\Forum\ForumSearchProvider;

#[GetCollection(
    uriTemplate: '/forums/search',
    openapi: new Operation(tags: ['Forum']),
    paginationEnabled: true,
    paginationItemsPerPage: 20,
    security: 'is_granted("PUBLIC_ACCESS")',
    normalizationContext: ['skip_null_values' => false],
    name: 'api_forum_search',
    provider: ForumSearchProvider::class,
)]
class ForumSearchResult
{
    #[ApiProperty(identifier: true)]
    public string $topicId;

    public string $topicSlug;

    public string $topicTitle;

    public int $topicPostNumber;

    public ?string $lastPostDatetime;

    public string $forumId;

    public string $forumTitle;

    public string $forumSlug;

    public string $categoryId;

    public string $categoryTitle;

    /** Plain-text snippet (~150 chars) of the best matching post, or null for title-only hits. */
    public ?string $postSnippet = null;

    /** ID of the post the snippet was taken from, when applicable. */
    public ?string $postId = null;

    /** "title", "post", or "both" — lets the client tweak UI presentation. */
    public string $matchType;

    /** Echo of the search term used. Frontend uses it to wrap matches in <mark>. */
    public string $term;
}
