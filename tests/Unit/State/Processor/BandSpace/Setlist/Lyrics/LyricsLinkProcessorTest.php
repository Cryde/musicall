<?php

declare(strict_types=1);

namespace App\Tests\Unit\State\Processor\BandSpace\Setlist\Lyrics;

use ApiPlatform\Metadata\Post;
use App\ApiResource\BandSpace\Setlist\Lyrics\LyricsLink;
use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\BandSpace\Lyrics;
use App\Entity\BandSpace\Song;
use App\Entity\User;
use App\Repository\BandSpace\LyricsRepository;
use App\Repository\BandSpace\SongRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Security\BandSpace\LyricsWriteGuard;
use App\Security\BandSpace\SongWriteGuard;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\BandSpace\Lyrics\LyricsSongLinker;
use App\Service\Builder\BandSpace\LyricsBuilder;
use App\State\Processor\BandSpace\Setlist\Lyrics\LyricsLinkProcessor;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class LyricsLinkProcessorTest extends TestCase
{
    /**
     * Two members linking different lyrics to the same song at once both find it free; the unique link
     * refuses the second. It must read as the usual « already has lyrics » 409, not a 500.
     */
    public function test_a_link_beaten_by_another_reads_as_a_song_that_has_lyrics(): void
    {
        $bandSpace = new BandSpace();
        $bandSpace->id = 'b0000000-0000-7000-8000-000000000001';
        $song = new Song();
        $song->id = 's0000000-0000-7000-8000-000000000001';
        $song->bandSpace = $bandSpace;
        $song->title = 'Enfin';
        $lyrics = new Lyrics();
        $lyrics->id = 'l0000000-0000-7000-8000-000000000001';
        $lyrics->bandSpace = $bandSpace;
        $lyrics->title = 'Idée';

        $memberChecker = $this->createStub(BandSpaceMemberChecker::class);
        $memberChecker->method('checkMemberForWrite')->willReturn([$bandSpace, new BandSpaceMembership()]);
        $songRepository = $this->createStub(SongRepository::class);
        $songRepository->method('findOneByIdAndBandSpace')->willReturn($song);
        $lyricsRepository = $this->createStub(LyricsRepository::class);
        $lyricsRepository->method('findOneByIdAndBandSpace')->willReturn($lyrics);
        $lyricsRepository->method('findOneBySong')->willReturn(null);
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn(new User());
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('flush')->willThrowException($this->createStub(UniqueConstraintViolationException::class));

        $processor = new LyricsLinkProcessor(
            $entityManager,
            $memberChecker,
            new LyricsWriteGuard(new SongWriteGuard()),
            $lyricsRepository,
            new LyricsSongLinker($songRepository, $lyricsRepository, new SongWriteGuard()),
            $this->createStub(BandSpaceActivityRecorder::class),
            $this->createStub(LyricsBuilder::class),
            $security,
        );

        $input = new LyricsLink();
        $input->songId = (string) $song->id;

        $this->expectException(ConflictHttpException::class);
        $this->expectExceptionMessage('band_space.lyrics.song_has_lyrics');

        $processor->process($input, new Post(), ['bandSpaceId' => $bandSpace->id, 'id' => $lyrics->id]);
    }
}
