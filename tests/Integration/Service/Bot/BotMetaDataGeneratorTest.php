<?php

namespace App\Tests\Integration\Service\Bot;

use App\Entity\Gallery;
use App\Entity\Publication;
use App\Entity\PublicationSubCategory;
use App\Service\Bot\BotMetaDataGenerator;
use App\Tests\Factory\Attribute\InstrumentFactory;
use App\Tests\Factory\Forum\ForumFactory;
use App\Tests\Factory\Forum\ForumTopicFactory;
use App\Tests\Factory\Musician\MusicianProfileFactory;
use App\Tests\Factory\Musician\MusicianProfileInstrumentFactory;
use App\Tests\Factory\Publication\GalleryFactory;
use App\Tests\Factory\Publication\GalleryImageFactory;
use App\Tests\Factory\Publication\PublicationCoverFactory;
use App\Tests\Factory\Publication\PublicationFactory;
use App\Tests\Factory\Publication\PublicationSubCategoryFactory;
use App\Tests\Factory\User\UserFactory;
use App\Tests\Factory\User\UserProfilePictureFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class BotMetaDataGeneratorTest extends KernelTestCase
{
    use ResetDatabase, Factories;

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
        $this->assertSame([], $this->botMetaDataGenerator->getMetaData('/u/dont-exists/musician'));
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
        $publication1->_real()->setCover($cover->_real());
        $publication1->_save();

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
        $course->_real()->setCover($cover->_real());
        $course->_save();

        $result = $this->botMetaDataGenerator->getMetaData('/cours/apprendre-la-guitare');
        $this->assertSame([
            'title'       => 'Apprendre la guitare',
            'description' => 'Un super cours de guitare pour débutants',
            'cover'       => 'http://localhost/media/cache/resolve/publication_image_filter/images/publication/cover/cover-course',
        ], $result);
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
        $gallery->_real()->setCoverImage($cover->_real());
        $gallery->_save();

        $result = $this->botMetaDataGenerator->getMetaData('/photos/cool-slug-for-gallery');
        $this->assertSame([
            'title'       => 'Ceci est titre gallery',
            'description' => 'Petite description de la gallery 1',
            'cover'       => 'http://localhost/media/cache/resolve/gallery_image_filter_full/images/gallery/' . $gallery->_real()->getId() . '/cover-gallery',
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

    public function test_get_metadata_for_musician_profile_not_found(): void
    {
        $this->assertSame([], $this->botMetaDataGenerator->getMetaData('/u/unknown-user/musician'));
    }

    public function test_get_metadata_for_musician_profile_without_instruments(): void
    {
        $user = UserFactory::new(['username' => 'musicien_test'])->create();
        MusicianProfileFactory::createOne(['user' => $user]);

        $result = $this->botMetaDataGenerator->getMetaData('/u/musicien_test/musician');
        $this->assertSame([
            'title'       => 'Profil musicien de musicien_test - MusicAll',
            'description' => 'Découvrez le profil musicien de musicien_test sur MusicAll.',
            'cover'       => null,
        ], $result);
    }

    public function test_get_metadata_for_musician_profile_with_instruments(): void
    {
        $user = UserFactory::new(['username' => 'guitariste_batteur'])->create();
        $musicianProfile = MusicianProfileFactory::createOne(['user' => $user]);

        $guitar = InstrumentFactory::new()->asGuitar()->create();
        $drum = InstrumentFactory::new()->asDrum()->create();

        MusicianProfileInstrumentFactory::createOne([
            'musicianProfile' => $musicianProfile,
            'instrument'      => $guitar,
        ]);
        MusicianProfileInstrumentFactory::createOne([
            'musicianProfile' => $musicianProfile,
            'instrument'      => $drum,
        ]);

        $result = $this->botMetaDataGenerator->getMetaData('/u/guitariste_batteur/musician');
        $this->assertSame([
            'title'       => 'Profil musicien de guitariste_batteur - MusicAll',
            'description' => 'Découvrez le profil musicien de guitariste_batteur sur MusicAll. Instruments : Guitare, Batterie.',
            'cover'       => null,
        ], $result);
    }

    public function test_get_metadata_for_musician_profile_with_profile_picture(): void
    {
        $user = UserFactory::new(['username' => 'musicien_photo'])->create();
        $profilePicture = UserProfilePictureFactory::createOne(['imageName' => 'photo-musicien.jpg', 'imageSize' => 10]);
        $user->_real()->setProfilePicture($profilePicture->_real());
        $user->_save();

        MusicianProfileFactory::createOne(['user' => $user]);

        $result = $this->botMetaDataGenerator->getMetaData('/u/musicien_photo/musician');
        $this->assertSame([
            'title'       => 'Profil musicien de musicien_photo - MusicAll',
            'description' => 'Découvrez le profil musicien de musicien_photo sur MusicAll.',
            'cover'       => 'http://localhost/media/cache/resolve/user_profile_picture_large/images/user/profile/photo-musicien.jpg',
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
        $this->assertSame([], $this->botMetaDataGenerator->getMetaData('/u/utilisateur-inexistant'));
    }

    public function test_get_metadata_for_user_profile_public(): void
    {
        $user = UserFactory::new(['username' => 'jean_dupont'])->create();
        $user->_real()->getProfile()->setDisplayName('Jean Dupont');
        $user->_real()->getProfile()->setBio('Guitariste passionné depuis 10 ans.');
        $user->_real()->getProfile()->setIsPublic(true);
        $user->_save();

        $result = $this->botMetaDataGenerator->getMetaData('/u/jean_dupont');
        $this->assertSame([
            'title'       => 'Jean Dupont - MusicAll',
            'description' => 'Guitariste passionné depuis 10 ans.',
            'cover'       => null,
        ], $result);
    }

    public function test_get_metadata_for_user_profile_private(): void
    {
        $user = UserFactory::new(['username' => 'utilisateur_prive'])->create();
        $user->_real()->getProfile()->setIsPublic(false);
        $user->_save();

        $result = $this->botMetaDataGenerator->getMetaData('/u/utilisateur_prive');
        $this->assertSame([
            'title'       => 'Profil privé - MusicAll',
            'description' => 'Ce profil est privé.',
        ], $result);
    }

    public function test_get_metadata_for_user_profile_with_picture(): void
    {
        $user = UserFactory::new(['username' => 'marie_photo'])->create();
        $user->_real()->getProfile()->setDisplayName('Marie Martin');
        $user->_real()->getProfile()->setIsPublic(true);
        $profilePicture = UserProfilePictureFactory::createOne(['imageName' => 'photo-marie.jpg', 'imageSize' => 10]);
        $user->_real()->setProfilePicture($profilePicture->_real());
        $user->_save();

        $result = $this->botMetaDataGenerator->getMetaData('/u/marie_photo');
        $this->assertSame([
            'title'       => 'Marie Martin - MusicAll',
            'description' => 'Découvrez le profil de Marie Martin sur MusicAll.',
            'cover'       => 'http://localhost/media/cache/resolve/user_profile_picture_large/images/user/profile/photo-marie.jpg',
        ], $result);
    }
}