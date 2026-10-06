<?php

declare(strict_types=1);

namespace App\Tests\Api\Moderation;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\User\MusicianAnnounceFactory;
use App\Tests\Factory\User\UserFactory;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/** What a suspension changes for the account itself and for everyone looking for it (#1116). */
#[ResetDatabase]
class SuspendedAccountTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_a_token_issued_before_the_suspension_is_refused(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $token = self::getContainer()->get(JWTTokenManagerInterface::class)->create($user);
        $user->suspensionDatetime = new \DateTimeImmutable();
        \Zenstruck\Foundry\Persistence\save($user);

        $this->client->request('GET', '/api/users/self', [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(['code' => 401, 'message' => 'account_suspended']);
    }

    public function test_a_valid_token_of_an_account_in_good_standing_still_works(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $token = self::getContainer()->get(JWTTokenManagerInterface::class)->create($user);

        $this->client->request('GET', '/api/users/self', [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);

        $this->assertResponseIsSuccessful();
    }

    public function test_the_public_profile_of_a_suspended_account_is_not_found(): void
    {
        UserFactory::new()->create([
            'username' => 'suspendu',
            'email' => 'suspendu@test.com',
            'suspensionDatetime' => new \DateTimeImmutable(),
        ]);

        $this->client->request('GET', '/api/user/profile/suspendu');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Profil non trouvé',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Profil non trouvé',
        ]);
    }

    public function test_announces_of_suspended_and_closed_accounts_leave_the_homepage(): void
    {
        $visible = MusicianAnnounceFactory::new(['note' => 'Visible'])->create();
        MusicianAnnounceFactory::new([
            'author' => UserFactory::new(['suspensionDatetime' => new \DateTimeImmutable()]),
            'note' => 'Suspendu',
        ])->create();
        MusicianAnnounceFactory::new([
            'author' => UserFactory::new(['deletionDatetime' => new \DateTimeImmutable()]),
            'note' => 'Supprimé',
        ])->create();

        $this->client->request('GET', '/api/musician_announces/last');

        $this->assertResponseIsSuccessful();
        $body = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertSame([$visible->id], array_column($body['member'], 'id'));
    }

    public function test_announces_of_suspended_and_closed_accounts_leave_musician_search(): void
    {
        $visible = MusicianAnnounceFactory::new(['type' => 1, 'note' => 'Visible'])->create();
        MusicianAnnounceFactory::new([
            'type' => 1,
            'author' => UserFactory::new(['suspensionDatetime' => new \DateTimeImmutable()]),
        ])->create();
        MusicianAnnounceFactory::new([
            'type' => 1,
            'author' => UserFactory::new(['deletionDatetime' => new \DateTimeImmutable()]),
        ])->create();

        $this->client->request('GET', '/api/musicians/search', ['type' => '1']);

        $this->assertResponseIsSuccessful();
        $body = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertSame([(string) $visible->id], array_column($body['member'], 'id'));
    }
}
