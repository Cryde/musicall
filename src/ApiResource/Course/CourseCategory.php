<?php

declare(strict_types=1);

namespace App\ApiResource\Course;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\Course\CourseCategoryProvider;

#[GetCollection(
    uriTemplate: '/course-categories',
    openapi: new Operation(tags: ['Courses']),
    paginationEnabled: false,
    name: 'api_course_categories_list',
    provider: CourseCategoryProvider::class,
)]
class CourseCategory
{
    public int $id;
    public string $title;
    public string $slug;
    public ?int $position;
}
