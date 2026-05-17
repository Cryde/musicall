<?php

declare(strict_types=1);

namespace App\Tests\Api\Teacher;

use App\Repository\Teacher\TeacherProfileRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Teacher\TeacherProfileFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class TeacherProfileDeleteTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_delete_teacher_profile(): void
    {
        $teacherProfileRepository = self::getContainer()->get(TeacherProfileRepository::class);
        $user = UserFactory::new()->asBaseUser()->create();

        TeacherProfileFactory::new()->create(['user' => $user]);

        $this->assertNotNull($teacherProfileRepository->findOneBy(['user' => $user]));

        $this->client->loginUser($user);
        $this->client->request('DELETE', '/api/user/teacher-profile');

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertNull($teacherProfileRepository->findOneBy(['user' => $user]));
    }

    public function test_delete_teacher_profile_not_logged_in(): void
    {
        $this->client->request('DELETE', '/api/user/teacher-profile');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function test_delete_teacher_profile_no_profile(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user);
        $this->client->request('DELETE', '/api/user/teacher-profile');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }
}
