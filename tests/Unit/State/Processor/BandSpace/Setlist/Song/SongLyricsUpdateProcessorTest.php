<?php

declare(strict_types=1);

namespace App\Tests\Unit\State\Processor\BandSpace\Setlist\Song;

use ApiPlatform\Metadata\Patch;
use App\ApiResource\BandSpace\Setlist\Song\SongLyrics;
use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\BandSpace\Song;
use App\Entity\User;
use App\Repository\BandSpace\LyricsRepository;
use App\Repository\BandSpace\SongRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Security\BandSpace\LyricsWriteGuard;
use App\Security\BandSpace\SongWriteGuard;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\Builder\BandSpace\SongLyricsBuilder;
use App\State\Processor\BandSpace\Setlist\Song\SongLyricsUpdateProcessor;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class SongLyricsUpdateProcessorTest extends TestCase
{
    /**
     * Two members saving a song's first lyrics at once both find none and both insert; the unique link
     * refuses the second. It must read as the usual stale copy 409, not a 500.
     */
    public function test_a_first_save_beaten_by_another_reads_as_a_stale_copy(): void
    {
        $bandSpace = new BandSpace();
        $bandSpace->id = 'b0000000-0000-7000-8000-000000000001';
        $song = new Song();
        $song->id = 's0000000-0000-7000-8000-000000000001';
        $song->bandSpace = $bandSpace;
        $song->title = 'Au clair de la lune';
        $user = new User();

        $memberChecker = $this->createStub(BandSpaceMemberChecker::class);
        $memberChecker->method('checkMemberForWrite')->willReturn([$bandSpace, new BandSpaceMembership()]);
        $songRepository = $this->createStub(SongRepository::class);
        $songRepository->method('findOneByIdAndBandSpace')->willReturn($song);
        $lyricsRepository = $this->createStub(LyricsRepository::class);
        $lyricsRepository->method('findOneBySong')->willReturn(null);
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('flush')->willThrowException($this->createStub(UniqueConstraintViolationException::class));

        $processor = new SongLyricsUpdateProcessor(
            $entityManager,
            $memberChecker,
            new SongWriteGuard(),
            new LyricsWriteGuard(new SongWriteGuard()),
            $songRepository,
            $lyricsRepository,
            $this->createStub(BandSpaceActivityRecorder::class),
            $this->createStub(SongLyricsBuilder::class),
            $security,
        );

        $input = new SongLyrics();
        $input->lyrics = '[C]Au clair de la lune';
        $input->expectedLyricsVersion = 1;

        $this->expectException(ConflictHttpException::class);
        $this->expectExceptionMessage('band_space.song.lyrics_version_conflict');

        $processor->process($input, new Patch(), ['bandSpaceId' => $bandSpace->id, 'id' => $song->id]);
    }
}
