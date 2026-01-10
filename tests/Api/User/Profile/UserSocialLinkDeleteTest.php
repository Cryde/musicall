<?php

declare(strict_types=1);

namespace App\Tests\Api\User\Profile;

use App\Enum\SocialPlatform;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\User\UserFactory;
use App\Tests\Factory\User\UserSocialLinkFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class UserSocialLinkDeleteTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_delete_social_link_success(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'deleteuser',
            'email' => 'deleteuser@test.com',
        ]);
        $profile = $user->getProfile();

        $link = UserSocialLinkFactory::new()->create([
            'profile' => $profile,
            'platform' => SocialPlatform::YOUTUBE,
            'url' => 'https://www.youtube.com/@todelete',
        ]);
        $linkId = $link->getId();

        $this->client->loginUser($user->_real());
        $this->client->request('DELETE', '/api/user/profile/social-links/' . $linkId);
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }

    public function test_delete_social_link_not_found(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'deletenotfound',
            'email' => 'deletenotfound@test.com',
        ]);

        $this->client->loginUser($user->_real());
        $this->client->request('DELETE', '/api/user/profile/social-links/999999');
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'status' => 404,
            'type' => '/errors/404',
            'detail' => 'Lien social non trouvé',
            'description' => 'Lien social non trouvé',
        ]);
    }

    public function test_delete_social_link_not_owner(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create([
            'username' => 'linkowner',
            'email' => 'linkowner@test.com',
        ]);
        $otherUser = UserFactory::new()->asBaseUser()->create([
            'username' => 'otheruser',
            'email' => 'otheruser@test.com',
        ]);
        $ownerProfile = $owner->getProfile();

        $link = UserSocialLinkFactory::new()->create([
            'profile' => $ownerProfile,
            'platform' => SocialPlatform::YOUTUBE,
            'url' => 'https://www.youtube.com/@ownerchannel',
        ]);

        $this->client->loginUser($otherUser->_real());
        $this->client->request('DELETE', '/api/user/profile/social-links/' . $link->getId());
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'status' => 403,
            'type' => '/errors/403',
            'detail' => 'Vous ne pouvez pas supprimer ce lien',
            'description' => 'Vous ne pouvez pas supprimer ce lien',
        ]);
    }
}
