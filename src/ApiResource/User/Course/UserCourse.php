<?php declare(strict_types=1);

namespace App\ApiResource\User\Course;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use App\State\Processor\User\Course\UserCourseDeleteProcessor;
use App\State\Provider\User\Course\UserCourseCollectionProvider;
use App\State\Provider\User\Course\UserCourseDeleteProvider;

#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/user/courses',
            openapi: new Operation(
                tags: ['User Courses'],
                parameters: [
                    new Parameter(name: 'page', in: 'query', description: 'Page number', required: false),
                    new Parameter(name: 'itemsPerPage', in: 'query', description: 'Number of items per page', required: false),
                    new Parameter(name: 'status', in: 'query', description: 'Filter by status (0=draft, 1=online, 2=pending)', required: false),
                    new Parameter(name: 'category', in: 'query', description: 'Filter by category ID', required: false),
                    new Parameter(name: 'sortBy', in: 'query', description: 'Sort field (title, creation_datetime, edition_datetime)', required: false),
                    new Parameter(name: 'sortOrder', in: 'query', description: 'Sort order (asc, desc)', required: false),
                ]
            ),
            paginationEnabled: true,
            paginationItemsPerPage: 10,
            paginationClientItemsPerPage: true,
            security: 'is_granted("IS_AUTHENTICATED_REMEMBERED")',
            name: 'api_user_courses_get_collection',
            provider: UserCourseCollectionProvider::class
        ),
        new Delete(
            uriTemplate: '/user/courses/{id}',
            openapi: new Operation(tags: ['User Courses']),
            security: 'is_granted("IS_AUTHENTICATED_REMEMBERED")',
            name: 'api_user_courses_delete',
            provider: UserCourseDeleteProvider::class,
            processor: UserCourseDeleteProcessor::class,
        ),
    ]
)]
class UserCourse
{
    #[ApiProperty(identifier: true)]
    public int $id;

    public string $title;

    public string $slug;

    public \DateTimeInterface $creationDatetime;

    public ?\DateTimeInterface $editionDatetime = null;

    public int $statusId;

    public string $statusLabel;

    public int $typeId;

    public string $typeLabel;

    #[ApiProperty(genId: false)]
    public ?UserCourseCategory $category = null;

    public ?string $coverUrl = null;
}
