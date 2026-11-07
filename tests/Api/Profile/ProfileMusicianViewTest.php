<?php

declare(strict_types=1);

namespace App\Tests\Api\Profile;

use App\Entity\Metric\View;
use App\Entity\Musician\MusicianProfile;
use App\Entity\User;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Musician\MusicianProfileFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/** A visit to a member's « Médias » counts as the musician page's visits did before it folded into the profile (#1317). */
#[ResetDatabase]
class ProfileMusicianViewTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_a_visit_counts_once_a_period(): void
    {
        $owner = $this->musician('julien');

        $this->client->request('POST', '/api/user/profile/julien/musician/views');
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame('', $this->client->getResponse()->getContent());
        // The same visitor again, within the period: still one view.
        $this->client->request('POST', '/api/user/profile/julien/musician/views');
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->assertSame(1, $this->viewCount($owner));
        $views = self::getContainer()->get(EntityManagerInterface::class)->getRepository(View::class)->findAll();
        $this->assertCount(1, $views);
        $this->assertSame('musician_profile', $views[0]->entityType);
        $this->assertSame((string) $owner->musicianProfile?->id, $views[0]->entityId);
    }

    public function test_the_owner_own_visit_does_not_count(): void
    {
        $owner = $this->musician('julien');

        $this->client->loginUser($owner);
        $this->client->request('POST', '/api/user/profile/julien/musician/views');

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame(0, $this->viewCount($owner));
    }

    public function test_a_member_without_a_musician_profile_has_no_musician_visits(): void
    {
        UserFactory::new()->asBaseUser()->create(['username' => 'julien', 'email' => 'julien@test.com']);

        $this->client->request('POST', '/api/user/profile/julien/musician/views');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'status' => 404,
            'type' => '/errors/404',
            'detail' => 'Profil musicien non trouvé',
            'description' => 'Profil musicien non trouvé',
        ]);
    }

    public function test_a_private_profile_counts_nothing(): void
    {
        $owner = $this->musician('julien');
        $owner->profile->isPublic = false;
        \Zenstruck\Foundry\Persistence\save($owner);

        $this->client->request('POST', '/api/user/profile/julien/musician/views');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'status' => 404,
            'type' => '/errors/profile-private',
            'detail' => 'Ce profil est privé',
            'description' => 'Ce profil est privé',
        ]);
        $this->assertSame(0, $this->viewCount($owner));
    }

    public function test_an_unknown_member_is_not_found(): void
    {
        $this->client->request('POST', '/api/user/profile/nobody/musician/views');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'status' => 404,
            'type' => '/errors/404',
            'detail' => 'Profil non trouvé',
            'description' => 'Profil non trouvé',
        ]);
    }

    private function musician(string $username): User
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => $username, 'email' => $username . '@test.com']);
        $user->musicianProfile = MusicianProfileFactory::new()->create(['user' => $user]);
        \Zenstruck\Foundry\Persistence\save($user);

        return $user;
    }

    private function viewCount(User $owner): int
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $profile = $entityManager->getRepository(MusicianProfile::class)->find($owner->musicianProfile?->id);

        return $profile?->viewCache?->count ?? 0;
    }
}
