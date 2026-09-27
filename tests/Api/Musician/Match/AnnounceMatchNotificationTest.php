<?php

declare(strict_types=1);

namespace App\Tests\Api\Musician\Match;

use App\Entity\Attribute\Instrument;
use App\Entity\Musician\MusicianAnnounce;
use App\Entity\User;
use App\Entity\User\UserNotificationPreference;
use App\Enum\Notification\NotificationType;
use App\Enum\User\UserEmailType;
use App\Repository\Notification\NotificationRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Attribute\InstrumentFactory;
use App\Tests\Factory\Attribute\StyleFactory;
use App\Tests\Factory\User\MusicianAnnounceFactory;
use App\Tests\Factory\User\UserEmailLogFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mime\Header\ParameterizedHeader;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/** A new announce tells the members whose announce it answers (#1082). */
#[ResetDatabase]
class AnnounceMatchNotificationTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array BRUSSELS = ['latitude' => '50.8503', 'longitude' => '4.3517'];
    private const array IXELLES = ['latitude' => '50.8333', 'longitude' => '4.3667'];
    private const array LIEGE = ['latitude' => '50.6326', 'longitude' => '5.5797'];

    public function test_the_member_whose_announce_is_answered_gets_a_notification_and_an_email(): void
    {
        $drum = InstrumentFactory::new()->asDrum()->create();
        $band = $this->user('legroupe');
        $answered = $this->announce($band, $drum, 'Ixelles', self::IXELLES);
        $farBand = $this->user('loin');
        $this->announce($farBand, $drum, 'Liège', self::LIEGE);
        $drummer = $this->user('batteur');

        $this->postDrummerAnnounce($drummer, $drum);

        $this->assertResponseIsSuccessful();
        $posted = $this->postedAnnounce($drummer);
        $notifications = $this->notificationsOf($band);
        $this->assertCount(1, $notifications);
        $this->assertSame(NotificationType::MusicianAnnounceMatch, $notifications[0]->type);
        $this->assertSame([
            'announce_id' => (string) $posted->id,
            'announce_type' => MusicianAnnounce::TYPE_BAND,
            'instrument_name' => 'Batteur',
            'location_name' => 'Bruxelles',
            'distance_km' => 2,
            'answered_announce_id' => (string) $answered->id,
            'answered_headline' => 'Groupe cherche un batteur',
            'actor_id' => (string) $drummer->id,
            'actor_username' => 'batteur',
            'actor_has_musician_profile' => false,
        ], $notifications[0]->payload);
        $this->assertCount(0, $this->notificationsOf($farBand));
        $this->assertCount(0, $this->notificationsOf($drummer));

        $this->assertEmailCount(1);
        $email = $this->getMailerMessage();
        $this->assertEmailAddressContains($email, 'To', 'legroupe@test.com');
        /** @var ParameterizedHeader $params */
        $params = $email->getHeaders()->get('params');
        $this->assertSame([
            'username' => 'legroupe',
            'author_username' => 'batteur',
            'announce_headline' => 'Batteur cherche un groupe',
            'location_name' => 'Bruxelles',
            // A mail header carries text.
            'distance_km' => '2',
            'answered_headline' => 'Groupe cherche un batteur',
            'profile_url' => 'http://musicall.test/u/batteur',
        ], $params->getParameters());
    }

    public function test_a_member_with_two_answered_announces_hears_about_it_once(): void
    {
        $drum = InstrumentFactory::new()->asDrum()->create();
        $band = $this->user('legroupe');
        $this->announce($band, $drum, 'Liège', ['latitude' => '50.7', 'longitude' => '4.4']);
        $closest = $this->announce($band, $drum, 'Ixelles', self::IXELLES);

        $this->postDrummerAnnounce($this->user('batteur'), $drum);

        $this->assertResponseIsSuccessful();
        $notifications = $this->notificationsOf($band);
        $this->assertCount(1, $notifications);
        $this->assertSame((string) $closest->id, $notifications[0]->payload['answered_announce_id']);
        $this->assertEmailCount(1);
    }

    public function test_no_email_when_the_member_turned_it_off(): void
    {
        $drum = InstrumentFactory::new()->asDrum()->create();
        $band = $this->user('legroupe');
        $this->announce($band, $drum, 'Ixelles', self::IXELLES);
        $preference = new UserNotificationPreference();
        $preference->user = $band;
        $preference->announceMatch = false;
        $band->notificationPreference = $preference;
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($preference);
        $entityManager->flush();

        $this->postDrummerAnnounce($this->user('batteur'), $drum);

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $this->notificationsOf($band));
        $this->assertEmailCount(0);
    }

    public function test_one_email_a_day_at_most(): void
    {
        $drum = InstrumentFactory::new()->asDrum()->create();
        $band = $this->user('legroupe');
        $this->announce($band, $drum, 'Ixelles', self::IXELLES);
        UserEmailLogFactory::new()->create(['user' => $band, 'emailType' => UserEmailType::ANNOUNCE_MATCH]);

        $this->postDrummerAnnounce($this->user('batteur'), $drum);

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $this->notificationsOf($band));
        $this->assertEmailCount(0);
    }

    private function user(string $username): User
    {
        return UserFactory::new()->create(['username' => $username, 'email' => $username . '@test.com']);
    }

    /** @param array{latitude: string, longitude: string} $point */
    private function announce(User $author, Instrument $instrument, string $city, array $point): MusicianAnnounce
    {
        return MusicianAnnounceFactory::new()->withInstrument($instrument)->create([
            'type' => MusicianAnnounce::TYPE_MUSICIAN,
            'author' => $author,
            'locationName' => $city,
            'latitude' => $point['latitude'],
            'longitude' => $point['longitude'],
            'creationDatetime' => new \DateTime('-1 week'),
        ]);
    }

    private function postDrummerAnnounce(User $drummer, Instrument $drum): void
    {
        $this->client->loginUser($drummer);
        $this->client->jsonRequest('POST', '/api/musician_announces', [
            'type' => MusicianAnnounce::TYPE_BAND,
            'instrument' => '/api/instruments/' . $drum->id,
            'styles' => ['/api/styles/' . StyleFactory::new()->asRock()->create()->id],
            'location_name' => 'Bruxelles',
            'longitude' => self::BRUSSELS['longitude'],
            'latitude' => self::BRUSSELS['latitude'],
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
    }

    private function postedAnnounce(User $author): MusicianAnnounce
    {
        /** @var MusicianAnnounce $announce */
        $announce = self::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(MusicianAnnounce::class)
            ->findOneBy(['author' => $author]);

        return $announce;
    }

    /** @return list<\App\Entity\Notification\Notification> */
    private function notificationsOf(User $user): array
    {
        return self::getContainer()->get(NotificationRepository::class)->findForRecipient($user, 10, 0);
    }
}
