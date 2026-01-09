<?php

declare(strict_types=1);

namespace App\Tests\Api\User;

use App\Entity\Image\UserProfilePicture;
use App\Repository\UserRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\User\UserFactory;
use App\Tests\Factory\User\UserProfilePictureFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class UserDeletePictureTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;
    use ResetDatabase;
    use Factories;

    public function test_delete_picture(): void
    {
        $user = UserFactory::new()->asBaseUser()->create()->_real();
        $profilePicture = UserProfilePictureFactory::new()->create([
            'user' => $user,
            'imageName' => 'test-picture.jpg',
            'imageSize' => 12345,
        ])->_real();
        $user->setProfilePicture($profilePicture);

        // Flush to persist the bidirectional relationship
        $em = $this->getEntityManager();
        $em->flush();
        $em->clear();

        // Reload user without VichUploader file injection issues
        $user = static::getContainer()->get(UserRepository::class)->find($user->getId());

        // pretest
        $this->assertSame(1, $this->getProfilePictureCount());
        $this->assertNotNull($user->getProfilePicture());

        $this->client->loginUser($user);
        $this->client->request('DELETE', '/api/user/profile-picture');

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame(0, $this->getProfilePictureCount());
    }

    public function test_delete_picture_not_logged(): void
    {
        $this->client->request('DELETE', '/api/user/profile-picture');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals([
            'code' => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function test_delete_picture_user_has_no_picture(): void
    {
        $user = UserFactory::new()->asBaseUser()->create()->_real();

        // pretest
        $this->assertNull($user->getProfilePicture());
        $this->assertSame(0, $this->getProfilePictureCount());

        $this->client->loginUser($user);
        $this->client->request('DELETE', '/api/user/profile-picture');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertSame(0, $this->getProfilePictureCount());
    }

    private function getProfilePictureCount(): int
    {
        return $this->getEntityManager()
            ->getRepository(UserProfilePicture::class)
            ->count([]);
    }

    private function getEntityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }
}
