<?php

namespace App\Tests\Integration\Service\Bot;

use App\Entity\Gallery;
use App\Entity\Publication;
use App\Entity\PublicationSubCategory;
use App\Entity\Service\Service;
use App\Entity\Service\ServiceOffer;
use App\Entity\Service\ServicePlace;
use App\Enum\Service\OfferUnit;
use App\Enum\Service\ServicePlaceType;
use App\Enum\Service\ServiceStatus;
use App\Enum\Teacher\SessionDuration;
use App\Locale\LocalizedPath;
use App\Service\Bot\BotMetaDataGenerator;
use App\Service\Bot\Provider\CourseMetaDataProvider;
use App\Service\Bot\Provider\InstrumentSearchMetaDataProvider;
use App\Service\Bot\Provider\MusicianSearchMetaDataProvider;
use App\Tests\Factory\Attribute\InstrumentFactory;
use App\Tests\Factory\Forum\ForumFactory;
use App\Tests\Factory\Forum\ForumTopicFactory;
use App\Tests\Factory\Musician\MusicianProfileFactory;
use App\Tests\Factory\Publication\GalleryFactory;
use App\Tests\Factory\Publication\GalleryImageFactory;
use App\Tests\Factory\Publication\PublicationCoverFactory;
use App\Tests\Factory\Publication\PublicationFactory;
use App\Tests\Factory\Publication\PublicationSubCategoryFactory;
use App\Tests\Factory\Service\ServiceFactory;
use App\Tests\Factory\Service\ServiceInstrumentFactory;
use App\Tests\Factory\User\UserFactory;
use App\Tests\Factory\User\UserProfilePictureFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Translation\LocaleSwitcher;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class BotMetaDataGeneratorTest extends KernelTestCase
{
    private BotMetaDataGenerator $botMetaDataGenerator;

    protected function setUp(): void
    {
        $this->botMetaDataGenerator = static::getContainer()->get(BotMetaDataGenerator::class);
        parent::setUp();
    }

    public function test_get_metadata_with_wrong_uri(): void
    {
        $this->assertSame([], $this->botMetaDataGenerator->getMetaData('wrong-url'));
    }

    public function test_get_metadata_with_no_result(): void
    {
        $this->assertSame([], $this->botMetaDataGenerator->getMetaData('/publications/dont-exists'));
        $this->assertSame([], $this->botMetaDataGenerator->getMetaData('/photos/dont-exists'));
        $this->assertSame([], $this->botMetaDataGenerator->getMetaData('/cours/dont-exists'));
    }

    public function test_get_metadata_for_publication(): void
    {
        $publication1 = PublicationFactory::new([
            'status'              => Publication::STATUS_ONLINE,
            'publicationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2000-01-02T02:03:04+00:00'),
            'title'               => 'Ceci est titre1 publication',
            'shortDescription'    => 'Petite description de la publication 1',
            'slug'                => 'cool-slug-for-publication',
            'type'                => Publication::TYPE_TEXT,
        ])->create();
        $cover = PublicationCoverFactory::createOne(['imageName' => 'cover-publication', 'imageSize' => 10, 'publication' => $publication1]);
        $publication1->cover = $cover;
        \Zenstruck\Foundry\Persistence\save($publication1);

        $result = $this->botMetaDataGenerator->getMetaData('/publications/cool-slug-for-publication');
        $this->assertSame([
            'title'       => 'Ceci est titre1 publication',
            'description' => 'Petite description de la publication 1',
            'cover'       => 'http://localhost/media/cache/resolve/publication_image_filter/images/publication/cover/cover-publication',
        ], $result);
    }

    public function test_get_metadata_for_publications_base(): void
    {
        $result = $this->botMetaDataGenerator->getMetaData('/publications');
        $this->assertSame([
            'title'       => 'Publications - MusicAll',
            'description' => 'Découvrez toutes les publications sur MusicAll : actualités musicales, tests de matériel, interviews et bien plus encore.',
        ], $result);
    }

    public function test_get_metadata_for_publication_category(): void
    {
        PublicationSubCategoryFactory::createOne([
            'title' => 'News',
            'slug'  => 'news',
            'type'  => PublicationSubCategory::TYPE_PUBLICATION,
        ]);

        $result = $this->botMetaDataGenerator->getMetaData('/publications/category/news');
        $this->assertSame([
            'title'       => 'News - Publications - MusicAll',
            'description' => 'Retrouvez toutes les publications de la catégorie News sur MusicAll.',
        ], $result);
    }

    public function test_get_metadata_for_publication_category_not_found(): void
    {
        $result = $this->botMetaDataGenerator->getMetaData('/publications/category/unknown');
        $this->assertSame([
            'title'       => 'Publications - MusicAll',
            'description' => 'Découvrez toutes les publications sur MusicAll : actualités musicales, tests de matériel, interviews et bien plus encore.',
        ], $result);
    }

    public function test_get_metadata_for_course(): void
    {
        $course = PublicationFactory::new([
            'status'              => Publication::STATUS_ONLINE,
            'publicationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2000-01-02T02:03:04+00:00'),
            'title'               => 'Apprendre la guitare',
            'shortDescription'    => 'Un super cours de guitare pour débutants',
            'slug'                => 'apprendre-la-guitare',
            'type'                => Publication::TYPE_TEXT,
        ])->create();
        $cover = PublicationCoverFactory::createOne(['imageName' => 'cover-course', 'imageSize' => 10, 'publication' => $course]);
        $course->cover = $cover;
        \Zenstruck\Foundry\Persistence\save($course);

        $result = $this->botMetaDataGenerator->getMetaData('/cours/apprendre-la-guitare');
        $this->assertSame([
            'title'       => 'Apprendre la guitare',
            'description' => 'Un super cours de guitare pour débutants',
            'cover'       => 'http://localhost/media/cache/resolve/publication_image_filter/images/publication/cover/cover-course',
        ], $result);
    }

    public function test_get_metadata_for_a_course_under_its_english_path(): void
    {
        $course = PublicationFactory::new([
            'status'              => Publication::STATUS_ONLINE,
            'publicationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2000-01-02T02:03:04+00:00'),
            'title'               => 'Learn the guitar',
            'shortDescription'    => 'A guitar lesson for beginners',
            'slug'                => 'learn-the-guitar',
            'type'                => Publication::TYPE_TEXT,
        ])->create();

        $this->assertSame([
            'title'       => 'Learn the guitar',
            'description' => 'A guitar lesson for beginners',
            'cover'       => null,
        ], $this->botMetaDataGenerator->getMetaData('/en/lessons/learn-the-guitar'));
        $this->assertSame([], $this->botMetaDataGenerator->getMetaData('/en/lessons/unknown-lesson'));
    }

    /**
     * The providers match the French paths, so an English one reads as its French twin: the prefix goes, and a
     * translated first segment is translated back.
     */
    public function test_get_metadata_for_the_english_version_of_a_page(): void
    {
        $this->assertSame($this->botMetaDataGenerator->getMetaData('/cours'), $this->botMetaDataGenerator->getMetaData('/en/lessons'));
        $this->assertSame($this->botMetaDataGenerator->getMetaData('/publications'), $this->botMetaDataGenerator->getMetaData('/en/publications'));
        $this->assertSame($this->botMetaDataGenerator->getMetaData('/band-space'), $this->botMetaDataGenerator->getMetaData('/en/band-space'));
        $this->assertSame([
            'title'       => 'Rechercher un guitariste - MusicAll',
            'description' => 'Trouvez un guitariste près de chez vous pour rejoindre votre groupe ou collaborer sur vos projets musicaux.',
        ], $this->botMetaDataGenerator->getMetaData('/en/find-a-guitarist'));
    }

    public function test_an_english_path_reads_as_nothing_while_french_is_the_only_enabled_locale(): void
    {
        $frenchOnly = new BotMetaDataGenerator(
            [
                static::getContainer()->get(CourseMetaDataProvider::class),
                static::getContainer()->get(InstrumentSearchMetaDataProvider::class),
                static::getContainer()->get(MusicianSearchMetaDataProvider::class),
            ],
            new LocalizedPath(['fr'], 'fr'),
        );

        $this->assertSame([], $frenchOnly->getMetaData('/lessons'));
        $this->assertSame([], $frenchOnly->getMetaData('/find-a-guitarist'));
        // Nor is a French path rewritten: a doubled slash matched no provider before #1192, and still does not.
        $this->assertSame([], $frenchOnly->getMetaData('/rechercher-un-musicien//'));
    }

    public function test_get_metadata_for_courses_base(): void
    {
        $result = $this->botMetaDataGenerator->getMetaData('/cours');
        $this->assertSame([
            'title'       => 'Cours de musique - MusicAll',
            'description' => 'Apprenez la musique avec nos cours gratuits : guitare, basse, batterie, piano, chant et bien plus encore.',
        ], $result);
    }

    public function test_get_metadata_for_course_category(): void
    {
        PublicationSubCategoryFactory::createOne([
            'title' => 'Guitare',
            'slug'  => 'guitare',
            'type'  => PublicationSubCategory::TYPE_COURSE,
        ]);

        $result = $this->botMetaDataGenerator->getMetaData('/cours/category/guitare');
        $this->assertSame([
            'title'       => 'Guitare - Cours - MusicAll',
            'description' => 'Retrouvez tous les cours de Guitare sur MusicAll.',
        ], $result);
    }

    public function test_get_metadata_for_course_category_not_found(): void
    {
        $result = $this->botMetaDataGenerator->getMetaData('/cours/category/unknown');
        $this->assertSame([
            'title'       => 'Cours de musique - MusicAll',
            'description' => 'Apprenez la musique avec nos cours gratuits : guitare, basse, batterie, piano, chant et bien plus encore.',
        ], $result);
    }

    public function test_get_metadata_for_gallery(): void
    {
        $gallery = GalleryFactory::new([
            'status'              => Gallery::STATUS_ONLINE,
            'publicationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2000-01-02T02:03:04+00:00'),
            'title'               => 'Ceci est titre gallery',
            'description'         => 'Petite description de la gallery 1',
            'slug'                => 'cool-slug-for-gallery',
        ])->create();
        $cover = GalleryImageFactory::createOne(['imageName' => 'cover-gallery', 'imageSize' => 10, 'gallery' => $gallery]);
        $gallery->coverImage = $cover;
        \Zenstruck\Foundry\Persistence\save($gallery);

        $result = $this->botMetaDataGenerator->getMetaData('/photos/cool-slug-for-gallery');
        $this->assertSame([
            'title'       => 'Ceci est titre gallery',
            'description' => 'Petite description de la gallery 1',
            'cover'       => 'http://localhost/media/cache/resolve/gallery_image_filter_full/images/gallery/' . $gallery->id . '/cover-gallery',
        ], $result);
    }

    public function test_get_metadata_for_musician_search(): void
    {
        $result = $this->botMetaDataGenerator->getMetaData('/rechercher-un-musicien');
        $this->assertSame([
            'title'       => 'Rechercher un musicien - MusicAll',
            'description' => 'Trouvez des musiciens près de chez vous : guitaristes, batteurs, bassistes, chanteurs... Rejoignez un groupe ou formez le vôtre !',
        ], $result);
    }

    public function test_get_metadata_for_publication_without_cover(): void
    {
        PublicationFactory::createOne([
            'status'              => Publication::STATUS_ONLINE,
            'publicationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2000-01-02T02:03:04+00:00'),
            'title'               => 'Publication sans cover',
            'shortDescription'    => 'Description de la publication',
            'slug'                => 'publication-sans-cover',
            'type'                => Publication::TYPE_TEXT,
            'cover'               => null,
        ]);

        $result = $this->botMetaDataGenerator->getMetaData('/publications/publication-sans-cover');
        $this->assertSame([
            'title'       => 'Publication sans cover',
            'description' => 'Description de la publication',
            'cover'       => null,
        ], $result);
    }

    public function test_get_metadata_for_gallery_without_cover(): void
    {
        GalleryFactory::createOne([
            'status'              => Gallery::STATUS_ONLINE,
            'publicationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2000-01-02T02:03:04+00:00'),
            'title'               => 'Gallery sans cover',
            'description'         => 'Description de la gallery',
            'slug'                => 'gallery-sans-cover',
            'coverImage'          => null,
        ]);

        $result = $this->botMetaDataGenerator->getMetaData('/photos/gallery-sans-cover');
        $this->assertSame([
            'title'       => 'Gallery sans cover',
            'description' => 'Description de la gallery',
            'cover'       => null,
        ], $result);
    }

    public function test_get_metadata_for_forum_base(): void
    {
        $result = $this->botMetaDataGenerator->getMetaData('/forums');
        $this->assertSame([
            'title'       => 'Forum - MusicAll',
            'description' => 'Rejoignez la communauté MusicAll et échangez avec des passionnés de musique sur notre forum.',
        ], $result);
    }

    public function test_get_metadata_for_forum(): void
    {
        ForumFactory::createOne([
            'title'       => 'Guitare électrique',
            'slug'        => 'guitare-electrique',
            'description' => 'Discussions sur les guitares électriques et leur utilisation.',
        ]);

        $result = $this->botMetaDataGenerator->getMetaData('/forums/guitare-electrique');
        $this->assertSame([
            'title'       => 'Guitare électrique - Forum - MusicAll',
            'description' => 'Discussions sur les guitares électriques et leur utilisation.',
        ], $result);
    }

    public function test_get_metadata_for_forum_not_found(): void
    {
        $result = $this->botMetaDataGenerator->getMetaData('/forums/forum-inexistant');
        $this->assertSame([
            'title'       => 'Forum - MusicAll',
            'description' => 'Rejoignez la communauté MusicAll et échangez avec des passionnés de musique sur notre forum.',
        ], $result);
    }

    public function test_get_metadata_for_forum_topic(): void
    {
        $forum = ForumFactory::createOne([
            'title' => 'Amplis',
            'slug'  => 'amplis',
        ]);

        ForumTopicFactory::createOne([
            'title' => 'Quel ampli pour débuter ?',
            'slug'  => 'quel-ampli-pour-debuter',
            'forum' => $forum,
        ]);

        $result = $this->botMetaDataGenerator->getMetaData('/forums/topic/quel-ampli-pour-debuter');
        $this->assertSame([
            'title'       => 'Quel ampli pour débuter ? - Forum - MusicAll',
            'description' => 'Discussion "Quel ampli pour débuter ?" dans le forum Amplis sur MusicAll.',
        ], $result);
    }

    public function test_get_metadata_for_forum_topic_not_found(): void
    {
        $result = $this->botMetaDataGenerator->getMetaData('/forums/topic/topic-inexistant');
        $this->assertSame([
            'title'       => 'Forum - MusicAll',
            'description' => 'Rejoignez la communauté MusicAll et échangez avec des passionnés de musique sur notre forum.',
        ], $result);
    }

    public function test_get_metadata_for_user_profile_not_found(): void
    {
        $this->assertSame(['robots' => 'noindex, follow'], $this->botMetaDataGenerator->getMetaData('/u/utilisateur-inexistant'));
    }

    public function test_get_metadata_for_user_profile_public(): void
    {
        $user = UserFactory::new(['username' => 'jean_dupont'])->create();
        $user->profile->displayName = 'Jean Dupont';
        $user->profile->bio = 'Guitariste passionné depuis 10 ans.';
        $user->profile->isPublic = true;
        \Zenstruck\Foundry\Persistence\save($user);

        $result = $this->botMetaDataGenerator->getMetaData('/u/jean_dupont');
        $this->assertSame([
            'title'       => 'Jean Dupont - MusicAll',
            'description' => 'Guitariste passionné depuis 10 ans.',
            'cover'       => null,
            'json_ld'     => [
                '@context'      => 'https://schema.org',
                '@type'         => 'Person',
                'name'          => 'Jean Dupont',
                'alternateName' => '@jean_dupont',
                'url'           => 'http://localhost/u/jean_dupont',
                'description'   => 'Guitariste passionné depuis 10 ans.',
            ],
        ], $result);
    }

    public function test_get_metadata_for_user_profile_private(): void
    {
        $user = UserFactory::new(['username' => 'utilisateur_prive'])->create();
        $user->profile->isPublic = false;
        \Zenstruck\Foundry\Persistence\save($user);

        $result = $this->botMetaDataGenerator->getMetaData('/u/utilisateur_prive');
        $this->assertSame([
            'title'       => 'Profil privé - MusicAll',
            'description' => 'Ce profil est privé.',
            'robots'      => 'noindex, follow',
        ], $result);
    }

    public function test_get_metadata_for_a_private_teacher_profile(): void
    {
        $user = UserFactory::new(['username' => 'prof_prive'])->create();
        $user->profile->isPublic = false;
        \Zenstruck\Foundry\Persistence\save($user);
        ServiceFactory::new()->teaching()->create(['user' => $user]);

        $this->assertSame([
            'title'       => 'Profil privé - MusicAll',
            'description' => 'Ce profil est privé.',
            'robots'      => 'noindex, follow',
        ], $this->botMetaDataGenerator->getMetaData('/u/prof_prive/teacher'));
    }

    /** A draft is its owner's only (#1406): a search engine never indexes it. */
    public function test_get_metadata_for_a_draft_teacher_profile_is_noindex_and_nothing_else(): void
    {
        $user = UserFactory::new(['username' => 'prof_brouillon'])->create();
        ServiceFactory::new()->teaching()->create(['user' => $user, 'status' => ServiceStatus::Draft, 'headline' => 'Cours de guitare']);

        $this->assertSame(['robots' => 'noindex, follow'], $this->botMetaDataGenerator->getMetaData('/u/prof_brouillon/teacher'));
    }

    public function test_get_metadata_for_a_deleted_member_is_noindex_and_nothing_else(): void
    {
        $user = UserFactory::new(['username' => 'membre_parti'])->create();
        $user->deletionDatetime = new \DateTimeImmutable('2026-10-01T10:00:00+00:00');
        \Zenstruck\Foundry\Persistence\save($user);
        MusicianProfileFactory::createOne(['user' => $user]);
        ServiceFactory::new()->teaching()->create(['user' => $user]);

        $this->assertSame(['robots' => 'noindex, follow'], $this->botMetaDataGenerator->getMetaData('/u/membre_parti'));
        $this->assertSame(['robots' => 'noindex, follow'], $this->botMetaDataGenerator->getMetaData('/u/membre_parti/teacher'));
    }

    public function test_get_metadata_for_a_teacher(): void
    {
        $teacherProfile = $this->teacherWithPlacesAndPrices('julien_roche');

        $this->assertSame([
            'title'       => 'Cours de musique avec Julien Roche : guitare, basse, à Ixelles - MusicAll',
            'description' => "Julien Roche donne des cours sur MusicAll : guitare, basse. À partir de 25\u{a0}€ les 30 min. Premier cours offert.",
            'cover'       => null,
            'json_ld'     => [
                '@context'    => 'https://schema.org',
                '@type'       => 'Service',
                'serviceType' => 'Cours de musique : Guitare, Basse',
                'url'         => 'http://localhost/u/julien_roche/teacher',
                'description' => 'Joue les morceaux que tu aimes',
                'provider'    => [
                    '@type'         => 'Person',
                    'name'          => 'Julien Roche',
                    'alternateName' => '@julien_roche',
                    'url'           => 'http://localhost/u/julien_roche',
                    'homeLocation'  => ['@type' => 'Place', 'name' => 'Bruxelles'],
                ],
                'areaServed'  => ['Ixelles', 'En ligne'],
                'offers'      => [
                    ['@type' => 'Offer', 'name' => '60 min', 'price' => '40.00', 'priceCurrency' => 'EUR'],
                    ['@type' => 'Offer', 'name' => '30 min', 'price' => '25.00', 'priceCurrency' => 'EUR'],
                    ['@type' => 'Offer', 'name' => "Cours d'essai", 'price' => '0.00', 'priceCurrency' => 'EUR'],
                ],
            ],
        ], $this->botMetaDataGenerator->getMetaData('/u/julien_roche/teacher'));
        $this->assertSame('julien_roche', $teacherProfile->user->username);
    }

    public function test_get_metadata_for_a_teacher_with_no_place_no_price_no_instrument(): void
    {
        $user = UserFactory::new(['username' => 'prof_simple'])->create();
        $user->profile->location = null;
        \Zenstruck\Foundry\Persistence\save($user);
        ServiceFactory::new()->teaching()->create(['user' => $user, 'headline' => null, 'offersTrial' => false]);

        $this->assertSame([
            'title'       => 'Cours de musique avec prof_simple : musique - MusicAll',
            'description' => 'prof_simple donne des cours sur MusicAll : musique.',
            'cover'       => null,
            'json_ld'     => [
                '@context'    => 'https://schema.org',
                '@type'       => 'Service',
                'serviceType' => 'Cours de musique : Musique',
                'url'         => 'http://localhost/u/prof_simple/teacher',
                'provider'    => [
                    '@type'         => 'Person',
                    'name'          => 'prof_simple',
                    'alternateName' => '@prof_simple',
                    'url'           => 'http://localhost/u/prof_simple',
                ],
            ],
        ], $this->botMetaDataGenerator->getMetaData('/u/prof_simple/teacher'));
    }

    public function test_page_metadata_follows_the_locale_it_runs_in(): void
    {
        $this->teacherWithPlacesAndPrices('julien_en');

        $result = static::getContainer()->get(LocaleSwitcher::class)->runWithLocale(
            'en',
            fn (): array => $this->botMetaDataGenerator->getPageMetaData('/en/u/julien_en/teacher'),
        );

        $this->assertSame('Music lessons with Julien Roche: guitar, bass, in Ixelles - MusicAll', $result['title']);
        $this->assertSame('Julien Roche teaches on MusicAll: guitar, bass. From €25 for 30 min. First lesson free.', $result['description']);
        $this->assertSame('http://localhost/en/u/julien_en/teacher', $result['json_ld']['url']);
        $this->assertSame(['Ixelles', 'Online'], $result['json_ld']['areaServed']);
    }

    public function test_the_teacher_search_names_its_instrument_and_city(): void
    {
        InstrumentFactory::new()->asBass()->create();

        $this->assertSame([
            'title' => 'Prof de basse à Bruxelles - MusicAll',
            'description' => "Trouve un prof de basse à Bruxelles et autour, ou en ligne : compare les niveaux, les prix et les cours d'essai offerts sur MusicAll.",
        ], $this->botMetaDataGenerator->getPageMetaData('/rechercher-un-professeur?instrument=basse&location=Bruxelles&lat=50.8503&lng=4.3517&radius=10&level=beginner'));
    }

    public function test_the_teacher_search_elides_before_a_vowel(): void
    {
        InstrumentFactory::new()->create(['name' => 'Orgue', 'musicianName' => 'Organiste', 'slug' => 'orgue']);

        $this->assertSame([
            'title' => "Prof d'orgue - MusicAll",
            'description' => "Trouve un prof d'orgue près de chez toi, ou en ligne : compare les niveaux, les prix et les cours d'essai offerts sur MusicAll.",
        ], $this->botMetaDataGenerator->getPageMetaData('/rechercher-un-professeur?instrument=orgue'));
    }

    public function test_the_teacher_search_names_only_a_known_instrument_and_a_located_plain_city(): void
    {
        $default = [
            'title' => 'Trouver un prof de musique - MusicAll',
            'description' => "Des musiciens qui donnent des cours près de chez toi, ou en ligne : compare les niveaux, les prix et les cours d'essai offerts sur MusicAll.",
        ];

        $this->assertSame($default, $this->botMetaDataGenerator->getPageMetaData('/rechercher-un-professeur'));
        $this->assertSame($default, $this->botMetaDataGenerator->getPageMetaData('/rechercher-un-professeur?instrument=unknown'));
        // A city without the point the search goes by is not a search anybody can run.
        $this->assertSame($default, $this->botMetaDataGenerator->getPageMetaData('/rechercher-un-professeur?location=Bruxelles'));
        $this->assertSame($default, $this->botMetaDataGenerator->getPageMetaData('/rechercher-un-professeur?location=' . rawurlencode('<b>Cheap</b> pills') . '&lat=50.85&lng=4.35'));
        $this->assertSame('Prof de musique à Liège - MusicAll', $this->botMetaDataGenerator->getPageMetaData('/rechercher-un-professeur?location=Li%C3%A8ge&lat=50.63&lng=5.57')['title']);
    }

    public function test_the_english_teacher_search_is_described_in_english(): void
    {
        InstrumentFactory::new()->asBass()->create();

        $result = static::getContainer()->get(LocaleSwitcher::class)->runWithLocale(
            'en',
            fn (): array => $this->botMetaDataGenerator->getPageMetaData('/en/find-a-teacher?instrument=basse&location=Brussels&lat=50.8503&lng=4.3517'),
        );

        $this->assertSame([
            'title' => 'Bass teacher in Brussels - MusicAll',
            'description' => 'Find bass teachers in and around Brussels, or online: compare levels, prices and free trial lessons on MusicAll.',
        ], $result);
    }

    public function test_get_metadata_for_a_sound_engineer(): void
    {
        $this->soundEngineer('julien_son');

        $this->assertSame([
            'title'       => 'Julien Roche, ingénieur du son à Forest : mixage, enregistrement - MusicAll',
            'description' => "Ta démo mixée comme un disque. À partir de 80\u{a0}€ le titre.",
            'cover'       => null,
            'json_ld'     => [
                '@context'    => 'https://schema.org',
                '@type'       => 'Service',
                'serviceType' => 'Ingénieur du son : mixage, enregistrement',
                'url'         => 'http://localhost/u/julien_son/sound-engineer',
                'description' => 'Ta démo mixée comme un disque',
                'provider'    => [
                    '@type'         => 'Person',
                    'name'          => 'Julien Roche',
                    'alternateName' => '@julien_son',
                    'url'           => 'http://localhost/u/julien_son',
                    'homeLocation'  => ['@type' => 'Place', 'name' => 'Bruxelles'],
                ],
                'areaServed'  => ['Forest', 'À distance'],
                'offers'      => [
                    ['@type' => 'Offer', 'name' => "Mixage d'un titre", 'price' => '80.00', 'priceCurrency' => 'EUR'],
                    ['@type' => 'Offer', 'name' => 'EP de 5 titres', 'price' => '450.00', 'priceCurrency' => 'EUR'],
                ],
            ],
        ], $this->botMetaDataGenerator->getMetaData('/u/julien_son/sound-engineer'));
    }

    public function test_get_metadata_for_a_sound_engineer_with_nothing_filled_in(): void
    {
        $user = UserFactory::new(['username' => 'son_simple'])->create();
        $user->profile->location = null;
        \Zenstruck\Foundry\Persistence\save($user);
        ServiceFactory::new()->soundEngineering()->create(['user' => $user]);

        $this->assertSame([
            'title'       => 'son_simple, ingénieur du son : ingénieur du son - MusicAll',
            'description' => "son_simple propose ses services d'ingénieur du son sur MusicAll : ingénieur du son.",
            'cover'       => null,
            'json_ld'     => [
                '@context'    => 'https://schema.org',
                '@type'       => 'Service',
                'serviceType' => 'Ingénieur du son : ingénieur du son',
                'url'         => 'http://localhost/u/son_simple/sound-engineer',
                'provider'    => [
                    '@type'         => 'Person',
                    'name'          => 'son_simple',
                    'alternateName' => '@son_simple',
                    'url'           => 'http://localhost/u/son_simple',
                ],
            ],
        ], $this->botMetaDataGenerator->getMetaData('/u/son_simple/sound-engineer'));
    }

    public function test_get_metadata_for_a_draft_sound_engineer_is_noindex_and_nothing_else(): void
    {
        $user = UserFactory::new(['username' => 'son_brouillon'])->create();
        ServiceFactory::new()->soundEngineering()->create(['user' => $user, 'status' => ServiceStatus::Draft]);

        $this->assertSame(['robots' => 'noindex, follow'], $this->botMetaDataGenerator->getMetaData('/u/son_brouillon/sound-engineer'));
    }

    public function test_the_english_sound_engineer_page_is_described_in_english(): void
    {
        $this->soundEngineer('julien_sound');

        $result = static::getContainer()->get(LocaleSwitcher::class)->runWithLocale(
            'en',
            fn (): array => $this->botMetaDataGenerator->getPageMetaData('/en/u/julien_sound/sound-engineer'),
        );

        $this->assertSame('Julien Roche, sound engineer in Forest: mixing, recording - MusicAll', $result['title']);
        $this->assertSame('Ta démo mixée comme un disque. From €80 per track.', $result['description']);
        $this->assertSame('http://localhost/en/u/julien_sound/sound-engineer', $result['json_ld']['url']);
        $this->assertSame(['Forest', 'Remotely'], $result['json_ld']['areaServed']);
    }

    public function test_the_sound_engineer_search_is_described_in_both_locales(): void
    {
        $this->assertSame([
            'title' => 'Trouver un ingénieur du son - MusicAll',
            'description' => 'Mixage, mastering, enregistrement ou son en concert : trouve un ingénieur du son près de chez toi ou à distance, compare ses formules et écoute ses avant, après sur MusicAll.',
        ], $this->botMetaDataGenerator->getPageMetaData('/rechercher-un-ingenieur-du-son?speciality=mixing'));

        $result = static::getContainer()->get(LocaleSwitcher::class)->runWithLocale(
            'en',
            fn (): array => $this->botMetaDataGenerator->getPageMetaData('/en/find-a-sound-engineer'),
        );
        $this->assertSame('Find a sound engineer - MusicAll', $result['title']);
    }

    public function test_get_metadata_for_a_photographer(): void
    {
        $user = UserFactory::new(['username' => 'lea_photo'])->create();
        $user->profile->displayName = 'Léa Martin';
        $user->profile->location = 'Lyon';
        \Zenstruck\Foundry\Persistence\save($user);
        $service = ServiceFactory::new()->photography()->create([
            'user'         => $user,
            'headline'     => 'Tes concerts en images',
            'specialities' => ['concert', 'cover'],
        ]);
        $place = new ServicePlace();
        $place->type = ServicePlaceType::AtMyPlace;
        $place->city = 'Villeurbanne';
        $service->addPlace($place);
        foreach ([[OfferUnit::Flat, 60000, 'Séance pochette'], [OfferUnit::Event, 25000, 'Un concert']] as [$unit, $price, $title]) {
            $offer = new ServiceOffer();
            $offer->unit = $unit;
            $offer->price = $price;
            $offer->title = $title;
            $service->addOffer($offer);
        }
        \Zenstruck\Foundry\Persistence\save($service);

        $this->assertSame([
            'title'       => 'Léa Martin, photographe à Villeurbanne : concert, pochette - MusicAll',
            'description' => "Tes concerts en images. À partir de 250\u{a0}€ l'événement.",
            'cover'       => null,
            'json_ld'     => [
                '@context'    => 'https://schema.org',
                '@type'       => 'Service',
                'serviceType' => 'Photographe : concert, pochette',
                'url'         => 'http://localhost/u/lea_photo/photographer',
                'description' => 'Tes concerts en images',
                'provider'    => [
                    '@type'         => 'Person',
                    'name'          => 'Léa Martin',
                    'alternateName' => '@lea_photo',
                    'url'           => 'http://localhost/u/lea_photo',
                    'homeLocation'  => ['@type' => 'Place', 'name' => 'Lyon'],
                ],
                'areaServed'  => ['Villeurbanne'],
                'offers'      => [
                    ['@type' => 'Offer', 'name' => 'Un concert', 'price' => '250.00', 'priceCurrency' => 'EUR'],
                    ['@type' => 'Offer', 'name' => 'Séance pochette', 'price' => '600.00', 'priceCurrency' => 'EUR'],
                ],
            ],
        ], $this->botMetaDataGenerator->getMetaData('/u/lea_photo/photographer'));
    }

    public function test_get_metadata_for_a_videographer_with_nothing_filled_in(): void
    {
        $user = UserFactory::new(['username' => 'video_simple'])->create();
        $user->profile->location = null;
        \Zenstruck\Foundry\Persistence\save($user);
        ServiceFactory::new()->video()->create(['user' => $user]);

        $this->assertSame([
            'title'       => 'video_simple, vidéaste : vidéaste - MusicAll',
            'description' => 'video_simple propose ses services de vidéaste sur MusicAll : vidéaste.',
            'cover'       => null,
            'json_ld'     => [
                '@context'    => 'https://schema.org',
                '@type'       => 'Service',
                'serviceType' => 'Vidéaste : vidéaste',
                'url'         => 'http://localhost/u/video_simple/videographer',
                'provider'    => [
                    '@type'         => 'Person',
                    'name'          => 'video_simple',
                    'alternateName' => '@video_simple',
                    'url'           => 'http://localhost/u/video_simple',
                ],
            ],
        ], $this->botMetaDataGenerator->getMetaData('/u/video_simple/videographer'));
    }

    /** The kind is part of the address: a photographer has no videographer page to describe. */
    public function test_get_metadata_for_another_kind_is_noindex_and_nothing_else(): void
    {
        ServiceFactory::new()->photography()->create(['user' => UserFactory::new(['username' => 'lea_only_photo'])->create()]);

        $this->assertSame(['robots' => 'noindex, follow'], $this->botMetaDataGenerator->getMetaData('/u/lea_only_photo/videographer'));
    }

    public function test_the_photographer_and_videographer_searches_are_described_in_both_locales(): void
    {
        $this->assertSame([
            'title' => 'Trouver un photographe - MusicAll',
            'description' => 'Concert, photo promo, pochette ou studio : trouve un photographe près de chez toi, compare ses formules et regarde ses réalisations sur MusicAll.',
        ], $this->botMetaDataGenerator->getPageMetaData('/rechercher-un-photographe?speciality=concert'));
        $this->assertSame('Trouver un vidéaste - MusicAll', $this->botMetaDataGenerator->getPageMetaData('/rechercher-un-videaste')['title'] ?? null);

        $result = static::getContainer()->get(LocaleSwitcher::class)->runWithLocale(
            'en',
            fn (): array => $this->botMetaDataGenerator->getPageMetaData('/en/find-a-videographer'),
        );
        $this->assertSame('Find a videographer - MusicAll', $result['title'] ?? null);
    }

    /** Remote work only: no city in its title, « À distance » where it serves (#1412). */
    public function test_get_metadata_for_a_graphic_designer_working_remotely(): void
    {
        $user = UserFactory::new(['username' => 'nina_graphiste'])->create();
        $user->profile->displayName = 'Nina Roy';
        $user->profile->location = null;
        \Zenstruck\Foundry\Persistence\save($user);
        $service = ServiceFactory::new()->graphicDesign()->create([
            'user'         => $user,
            'headline'     => 'Ta pochette, du croquis au fichier imprimeur',
            'specialities' => ['cover', 'visual_identity'],
        ]);
        $place = new ServicePlace();
        $place->type = ServicePlaceType::Remote;
        $service->addPlace($place);
        $offer = new ServiceOffer();
        $offer->unit = OfferUnit::Flat;
        $offer->price = 35000;
        $offer->title = 'Une pochette';
        $service->addOffer($offer);
        \Zenstruck\Foundry\Persistence\save($service);

        $this->assertSame([
            'title'       => 'Nina Roy, graphiste : pochette, identité visuelle - MusicAll',
            'description' => "Ta pochette, du croquis au fichier imprimeur. À partir de 350\u{a0}€.",
            'cover'       => null,
            'json_ld'     => [
                '@context'    => 'https://schema.org',
                '@type'       => 'Service',
                'serviceType' => 'Graphiste : pochette, identité visuelle',
                'url'         => 'http://localhost/u/nina_graphiste/graphic-designer',
                'description' => 'Ta pochette, du croquis au fichier imprimeur',
                'provider'    => [
                    '@type'         => 'Person',
                    'name'          => 'Nina Roy',
                    'alternateName' => '@nina_graphiste',
                    'url'           => 'http://localhost/u/nina_graphiste',
                ],
                'areaServed'  => ['À distance'],
                'offers'      => [
                    ['@type' => 'Offer', 'name' => 'Une pochette', 'price' => '350.00', 'priceCurrency' => 'EUR'],
                ],
            ],
        ], $this->botMetaDataGenerator->getMetaData('/u/nina_graphiste/graphic-designer'));
    }

    public function test_get_metadata_for_a_webmaster_with_nothing_filled_in(): void
    {
        $user = UserFactory::new(['username' => 'web_simple'])->create();
        $user->profile->location = null;
        \Zenstruck\Foundry\Persistence\save($user);
        ServiceFactory::new()->web()->create(['user' => $user]);

        $this->assertSame([
            'title'       => 'web_simple, webmaster : webmaster - MusicAll',
            'description' => 'web_simple propose ses services de webmaster sur MusicAll : webmaster.',
            'cover'       => null,
            'json_ld'     => [
                '@context'    => 'https://schema.org',
                '@type'       => 'Service',
                'serviceType' => 'Webmaster : webmaster',
                'url'         => 'http://localhost/u/web_simple/webmaster',
                'provider'    => [
                    '@type'         => 'Person',
                    'name'          => 'web_simple',
                    'alternateName' => '@web_simple',
                    'url'           => 'http://localhost/u/web_simple',
                ],
            ],
        ], $this->botMetaDataGenerator->getMetaData('/u/web_simple/webmaster'));
    }

    public function test_get_metadata_for_a_webmaster_names_their_specialities(): void
    {
        $user = UserFactory::new(['username' => 'tom_web'])->create();
        $user->profile->displayName = 'Tom Petit';
        $user->profile->location = null;
        \Zenstruck\Foundry\Persistence\save($user);
        ServiceFactory::new()->web()->create([
            'user'         => $user,
            'headline'     => 'Le site de ton groupe, prêt pour ta sortie',
            'specialities' => ['band_site', 'online_store'],
        ]);

        $this->assertSame([
            'title'       => "Tom Petit, webmaster : site de groupe ou d'artiste, boutique en ligne - MusicAll",
            'description' => 'Le site de ton groupe, prêt pour ta sortie.',
            'cover'       => null,
            'json_ld'     => [
                '@context'    => 'https://schema.org',
                '@type'       => 'Service',
                'serviceType' => "Webmaster : site de groupe ou d'artiste, boutique en ligne",
                'url'         => 'http://localhost/u/tom_web/webmaster',
                'description' => 'Le site de ton groupe, prêt pour ta sortie',
                'provider'    => [
                    '@type'         => 'Person',
                    'name'          => 'Tom Petit',
                    'alternateName' => '@tom_web',
                    'url'           => 'http://localhost/u/tom_web',
                ],
            ],
        ], $this->botMetaDataGenerator->getMetaData('/u/tom_web/webmaster'));
    }

    public function test_the_graphic_designer_and_webmaster_searches_are_described_in_both_locales(): void
    {
        $this->assertSame([
            'title' => 'Trouver un graphiste - MusicAll',
            'description' => 'Pochette, logo, affiche ou merch : trouve un graphiste près de chez toi ou à distance, compare ses formules et regarde ses réalisations sur MusicAll.',
        ], $this->botMetaDataGenerator->getPageMetaData('/rechercher-un-graphiste?speciality=cover'));
        $this->assertSame('Trouver un webmaster - MusicAll', $this->botMetaDataGenerator->getPageMetaData('/rechercher-un-webmaster')['title'] ?? null);

        $result = static::getContainer()->get(LocaleSwitcher::class)->runWithLocale(
            'en',
            fn (): array => [
                $this->botMetaDataGenerator->getPageMetaData('/en/find-a-graphic-designer')['title'] ?? null,
                $this->botMetaDataGenerator->getPageMetaData('/en/find-a-webmaster')['title'] ?? null,
            ],
        );
        $this->assertSame(['Find a graphic designer - MusicAll', 'Find a webmaster - MusicAll'], $result);
    }

    public function test_page_metadata_only_asks_the_providers_ready_for_the_page(): void
    {
        $this->assertNotSame([], $this->botMetaDataGenerator->getMetaData('/cours'));
        $this->assertSame([], $this->botMetaDataGenerator->getPageMetaData('/cours'));
        $this->assertSame(['robots' => 'noindex, follow'], $this->botMetaDataGenerator->getPageMetaData('/u/nobody-here'));
    }

    private function soundEngineer(string $username): Service
    {
        $user = UserFactory::new(['username' => $username])->create();
        $user->profile->displayName = 'Julien Roche';
        $user->profile->location = 'Bruxelles';
        \Zenstruck\Foundry\Persistence\save($user);

        $service = ServiceFactory::new()->soundEngineering()->create([
            'user'         => $user,
            'headline'     => 'Ta démo mixée comme un disque',
            'specialities' => ['mixing', 'recording'],
        ]);
        foreach ([[ServicePlaceType::AtMyPlace, 'Forest'], [ServicePlaceType::Remote, null]] as [$type, $city]) {
            $place = new ServicePlace();
            $place->type = $type;
            $place->city = $city;
            $service->addPlace($place);
        }
        foreach ([[OfferUnit::Flat, 45000, 'EP de 5 titres'], [OfferUnit::Track, 8000, "Mixage d'un titre"]] as [$unit, $price, $title]) {
            $offer = new ServiceOffer();
            $offer->unit = $unit;
            $offer->price = $price;
            $offer->title = $title;
            $service->addOffer($offer);
        }
        \Zenstruck\Foundry\Persistence\save($service);

        return $service;
    }

    private function teacherWithPlacesAndPrices(string $username): Service
    {
        $user = UserFactory::new(['username' => $username])->create();
        $user->profile->displayName = 'Julien Roche';
        $user->profile->location = 'Bruxelles';
        \Zenstruck\Foundry\Persistence\save($user);

        $teacherProfile = ServiceFactory::new()->teaching()->create([
            'user'        => $user,
            'headline' => 'Joue les morceaux que tu aimes',
            'offersTrial' => true,
            'trialPrice'  => null,
        ]);
        ServiceInstrumentFactory::createOne(['service' => $teacherProfile, 'instrument' => InstrumentFactory::new()->asGuitar()->create()]);
        ServiceInstrumentFactory::createOne(['service' => $teacherProfile, 'instrument' => InstrumentFactory::new()->asBass()->create()]);

        foreach ([[ServicePlaceType::AtMyPlace, 'Ixelles'], [ServicePlaceType::Remote, null]] as [$type, $city]) {
            $place = new ServicePlace();
            $place->type = $type;
            $place->city = $city;
            $teacherProfile->addPlace($place);
        }
        foreach ([[SessionDuration::ONE_HOUR, 4000], [SessionDuration::THIRTY_MINUTES, 2500]] as [$duration, $price]) {
            $pricing = new ServiceOffer();
            $pricing->unit = OfferUnit::Lesson;
            $pricing->quantity = $duration->getMinutes();
            $pricing->price = $price;
            $teacherProfile->addOffer($pricing);
        }
        \Zenstruck\Foundry\Persistence\save($teacherProfile);

        return $teacherProfile;
    }

    public function test_get_metadata_for_user_profile_with_picture(): void
    {
        $user = UserFactory::new(['username' => 'marie_photo'])->create();
        $user->profile->displayName = 'Marie Martin';
        $user->profile->isPublic = true;
        $profilePicture = UserProfilePictureFactory::createOne(['imageName' => 'photo-marie.jpg', 'imageSize' => 10]);
        $user->profilePicture = $profilePicture;
        \Zenstruck\Foundry\Persistence\save($user);

        $result = $this->botMetaDataGenerator->getMetaData('/u/marie_photo');
        $this->assertSame([
            'title'       => 'Marie Martin - MusicAll',
            'description' => 'Découvrez le profil de Marie Martin sur MusicAll.',
            'cover'       => 'http://localhost/media/cache/resolve/user_profile_picture_large/images/user/profile/photo-marie.jpg',
            'json_ld'     => [
                '@context'      => 'https://schema.org',
                '@type'         => 'Person',
                'name'          => 'Marie Martin',
                'alternateName' => '@marie_photo',
                'url'           => 'http://localhost/u/marie_photo',
                'image'         => 'http://localhost/media/cache/resolve/user_profile_picture_large/images/user/profile/photo-marie.jpg',
            ],
        ], $result);
    }

    public function test_get_metadata_for_the_band_space_presentation(): void
    {
        $this->assertSame([
            'title'       => 'Band Space, l\'espace de travail de votre groupe - MusicAll',
            'description' => 'Réunissez l\'agenda, les tâches, les notes, les setlists, les fichiers et les finances de votre groupe dans un espace partagé. Gratuit avec votre compte MusicAll.',
        ], $this->botMetaDataGenerator->getMetaData('/band-space'));
    }

    public function test_get_metadata_for_the_band_space_presentation_ignores_a_trailing_slash(): void
    {
        $this->assertSame(
            $this->botMetaDataGenerator->getMetaData('/band-space'),
            $this->botMetaDataGenerator->getMetaData('/band-space/'),
        );
    }

    /** The static page must not answer for a band space URL that belongs to the authenticated app. */
    public function test_get_metadata_does_not_match_other_band_uris(): void
    {
        $this->assertSame([], $this->botMetaDataGenerator->getMetaData('/band'));
        $this->assertSame([], $this->botMetaDataGenerator->getMetaData('/band-space/extra'));
    }
}
