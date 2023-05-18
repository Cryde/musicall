<?php

namespace App\Tests\Unit\Serializer\Search;

use App\Entity\Attribute\Instrument;
use App\Entity\Attribute\Style;
use App\Entity\Musician\MusicianAnnounce;
use App\Entity\User;
use App\Serializer\Search\MusicianSearchArraySerializer;
use App\Serializer\User\UserArraySerializer;
use App\Serializer\User\UserProfilePictureArraySerializer;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

class MusicianSearchArraySerializerTest extends TestCase
{
    private MusicianSearchArraySerializer $musicianSearchArraySerializer;

    protected function setUp(): void
    {
        $this->musicianSearchArraySerializer = new MusicianSearchArraySerializer(
            new UserArraySerializer(
                new UserProfilePictureArraySerializer(
                    $this->createMock(UploaderHelper::class),
                    $this->createMock(CacheManager::class)
                )
            ),
            $this->buildHtmlSanitizerMock()
        );
        parent::setUp();
    }

    public function testToArray()
    {
        $author = (new User())
            ->setId('id_user')
            ->setUsername('username'); // todo maybe later test the profile picture
        $instrument = (new Instrument())->setName('Drum');
        $musicianAnnounce = (new MusicianAnnounce())
            ->setAuthor($author)
            ->setInstrument($instrument)
            ->setLocationName('Location name')
            ->setType(1)
            ->setLatitude('30.10')
            ->setLongitude('50.20')
            ->setNote('note_content');

        $musicianAnnounce->addStyle((new Style())->setName('Pop'));
        $musicianAnnounce->addStyle((new Style())->setName('Rock'));
        $this->assertSame([
            'id'            => null,
            'location_name' => 'Location name',
            'note'          => 'note_content',
            'user'          => [
                'id'       => 'id_user',
                'username' => 'username',
                'picture'  => null,
            ],
            'instrument'    => 'Drum',
            'type'          => 1,
            'styles'        => 'Pop, Rock',
        ], $this->musicianSearchArraySerializer->toArray($musicianAnnounce));
    }

    private function buildHtmlSanitizerMock(): HtmlSanitizerInterface
    {
        $mock = $this->createMock(HtmlSanitizerInterface::class);
        $mock->expects($this->once())
            ->method('sanitize')
            ->with('note_content')
            ->willReturn('note_content');

        return $mock;
    }
}