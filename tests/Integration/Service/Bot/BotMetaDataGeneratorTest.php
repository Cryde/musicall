<?php

namespace App\Tests\Integration\Service\Bot;

use App\Entity\Gallery;
use App\Entity\Publication;
use App\Service\Bot\BotMetaDataGenerator;
use App\Tests\Factory\Publication\GalleryFactory;
use App\Tests\Factory\Publication\GalleryImageFactory;
use App\Tests\Factory\Publication\PublicationCoverFactory;
use App\Tests\Factory\Publication\PublicationFactory;
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
        $this->assertSame([], $this->botMetaDataGenerator->getMetaData('/gallery/dont-exists'));
    }

    public function test_get_metadata_for_publications(): void
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

    public function test_get_metadata_for_galleries(): void
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

        $result = $this->botMetaDataGenerator->getMetaData('/gallery/cool-slug-for-gallery');
        $this->assertSame([
            'title'       => 'Ceci est titre gallery',
            'description' => 'Petite description de la gallery 1',
            'cover'       => 'http://localhost/media/cache/resolve/gallery_image_filter_full/images/gallery/' . $gallery->_real()->getId() . '/cover-gallery',
        ], $result);
    }
}