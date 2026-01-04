<?php

declare(strict_types=1);

namespace App\Tests\Api\Course;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Publication\PublicationSubCategoryFactory;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class CourseCategoryTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_get_course_categories(): void
    {
        $course1 = PublicationSubCategoryFactory::new()->asCourse()->create();
        $course2 = PublicationSubCategoryFactory::new()->asCourse2()->create();

        // Publication category - should not be returned
        PublicationSubCategoryFactory::new()->asNews()->create();

        $this->client->request('GET', '/api/course-categories');
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        $this->assertJsonEquals([
            '@context'   => '/api/contexts/CourseCategory',
            '@id'        => '/api/course-categories',
            '@type'      => 'Collection',
            'member'     => [
                [
                    '@id'      => '/api/course_categories/' . $course1->getId(),
                    '@type'    => 'CourseCategory',
                    'id'       => $course1->getId(),
                    'title'    => 'Cours',
                    'slug'     => 'cours',
                    'position' => 1,
                ],
                [
                    '@id'      => '/api/course_categories/' . $course2->getId(),
                    '@type'    => 'CourseCategory',
                    'id'       => $course2->getId(),
                    'title'    => 'Théorie',
                    'slug'     => 'theorie',
                    'position' => 2,
                ],
            ],
            'totalItems' => 2,
        ]);
    }
}
