<?php

declare(strict_types=1);

namespace App\Tests\Api\User\Profile;

use App\Enum\SocialPlatform;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\User\UserFactory;
use App\Tests\Factory\User\UserSocialLinkFactory;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class UserSocialLinkGetTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_get_social_links_empty(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'nolinksuser',
            'email' => 'nolinksuser@test.com',
        ]);

        $this->client->loginUser($user->_real());
        $this->client->request('GET', '/api/user/profile/social-links');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserSocialLinkResource',
            '@id' => '/api/user/profile/social-links',
            '@type' => 'Collection',
            'member' => [],
            'totalItems' => 0,
        ]);
    }

    public function test_get_social_links_success(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'linksuser',
            'email' => 'linksuser@test.com',
        ]);
        $profile = $user->getProfile();

        $link1 = UserSocialLinkFactory::new()->create([
            'profile' => $profile,
            'platform' => SocialPlatform::YOUTUBE,
            'url' => 'https://www.youtube.com/@testchannel',
        ]);
        $link2 = UserSocialLinkFactory::new()->create([
            'profile' => $profile,
            'platform' => SocialPlatform::INSTAGRAM,
            'url' => 'https://www.instagram.com/testuser',
        ]);

        $this->client->loginUser($user->_real());
        $this->client->request('GET', '/api/user/profile/social-links');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserSocialLinkResource',
            '@id' => '/api/user/profile/social-links',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/user_social_link_resources/' . $link1->getId(),
                    '@type' => 'UserSocialLinkResource',
                    'id' => $link1->getId(),
                    'platform' => 'youtube',
                    'platform_label' => 'YouTube',
                    'url' => 'https://www.youtube.com/@testchannel',
                ],
                [
                    '@id' => '/api/user_social_link_resources/' . $link2->getId(),
                    '@type' => 'UserSocialLinkResource',
                    'id' => $link2->getId(),
                    'platform' => 'instagram',
                    'platform_label' => 'Instagram',
                    'url' => 'https://www.instagram.com/testuser',
                ],
            ],
            'totalItems' => 2,
        ]);
    }
}
