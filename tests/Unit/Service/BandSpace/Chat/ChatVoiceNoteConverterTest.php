<?php declare(strict_types=1);

namespace App\Tests\Unit\Service\BandSpace\Chat;

use App\Exception\BandSpace\UnreadableVoiceNoteException;
use App\Service\BandSpace\Chat\ChatVoiceNoteConverter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Process\Process;

/**
 * Runs the real ffmpeg and ffprobe, on recordings a real browser's MediaRecorder produced: the
 * container labels are the whole difficulty here, and a hand-made file would not have them.
 */
class ChatVoiceNoteConverterTest extends TestCase
{
    private const string FIXTURES = __DIR__ . '/../../../../fixtures/voice_notes';

    /** @var string[] */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        $this->temporaryFiles = [];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function recordings(): iterable
    {
        // What libmagic reports for each, not what the browser labelled it.
        yield 'Chrome and Firefox, WebM Opus' => ['chrome-opus.webm', 'video/webm'];
        yield 'Chrome, MP4 Opus' => ['chrome-opus.mp4', 'video/mp4'];
    }

    #[DataProvider('recordings')]
    public function test_a_recording_becomes_mono_aac_with_its_measured_duration(string $fixture, string $detectedMimeType): void
    {
        $recording = new File(self::FIXTURES . '/' . $fixture);
        $this->assertSame($detectedMimeType, $recording->getMimeType());

        $converted = (new ChatVoiceNoteConverter())->convert($recording, $detectedMimeType);
        $this->temporaryFiles[] = $converted->file->getPathname();

        $this->assertSame(2, $converted->durationSeconds);
        $this->assertSame(['codec_name=aac', 'channels=1'], $this->probeAudio($converted->file->getPathname()));
        $this->assertCount(ChatVoiceNoteConverter::PEAK_COUNT, $converted->peaks);
    }

    /** Loud for its first half, silent for its second: the waveform has to say so. */
    public function test_the_peaks_follow_the_loudness_of_the_note(): void
    {
        $path = $this->generate('sine=frequency=440:duration=2,apad=pad_dur=2');

        $converted = (new ChatVoiceNoteConverter())->convert(new File($path), 'video/webm');
        $this->temporaryFiles[] = $converted->file->getPathname();

        $this->assertCount(ChatVoiceNoteConverter::PEAK_COUNT, $converted->peaks);
        $this->assertSame(ChatVoiceNoteConverter::PEAK_MAX, max($converted->peaks), 'The loudest slice is the top of the scale');
        [$firstHalf, $secondHalf] = array_chunk($converted->peaks, intdiv(ChatVoiceNoteConverter::PEAK_COUNT, 2));
        // The edges of the tone are softened by the encoder, so the middle of each half is compared.
        $this->assertGreaterThan(200, min(array_slice($firstHalf, 2, -2)));
        $this->assertLessThan(5, max(array_slice($secondHalf, 2, -2)));
    }

    public function test_a_silent_note_has_a_flat_waveform_rather_than_an_error(): void
    {
        $path = $this->generate('anullsrc=r=48000:cl=mono', seconds: 2);

        $converted = (new ChatVoiceNoteConverter())->convert(new File($path), 'video/webm');
        $this->temporaryFiles[] = $converted->file->getPathname();

        $this->assertSame(array_fill(0, ChatVoiceNoteConverter::PEAK_COUNT, 0), $converted->peaks);
    }

    public function test_a_recording_longer_than_the_limit_is_cut_at_it(): void
    {
        $path = $this->generate('anullsrc=r=48000:cl=mono', seconds: ChatVoiceNoteConverter::MAX_DURATION_SECONDS + 20);

        $converted = (new ChatVoiceNoteConverter())->convert(new File($path), 'video/webm');
        $this->temporaryFiles[] = $converted->file->getPathname();

        $this->assertSame(ChatVoiceNoteConverter::MAX_DURATION_SECONDS, $converted->durationSeconds);
    }

    /**
     * The demuxer is forced from the detected type, so a playlist or anything else dressed as a WebM
     * is refused rather than probed and followed.
     */
    public function test_something_that_is_not_a_recording_is_refused(): void
    {
        $path = $this->temporaryPath();
        file_put_contents($path, "#EXTM3U\n#EXT-X-MEDIA-SEQUENCE:0\nfile:///etc/passwd\n");

        $this->expectException(UnreadableVoiceNoteException::class);
        (new ChatVoiceNoteConverter())->convert(new File($path), 'video/webm');
    }

    public function test_a_type_with_no_demuxer_is_refused_before_ffmpeg_runs(): void
    {
        $this->expectException(UnreadableVoiceNoteException::class);
        (new ChatVoiceNoteConverter())->convert(new File(self::FIXTURES . '/chrome-opus.webm'), 'image/png');
    }

    /**
     * @return list<string>
     */
    private function probeAudio(string $path): array
    {
        $process = new Process([
            'ffprobe', '-v', 'error', '-select_streams', 'a:0',
            '-show_entries', 'stream=codec_name,channels', '-of', 'default=noprint_wrappers=1', $path,
        ]);
        $process->mustRun();

        return array_values(array_filter(explode("\n", trim($process->getOutput()))));
    }

    /** A WebM Opus file from an ffmpeg test source, standing in for a recording. */
    private function generate(string $source, ?int $seconds = null): string
    {
        $path = $this->temporaryPath() . '.webm';
        $this->temporaryFiles[] = $path;
        $command = ['ffmpeg', '-nostdin', '-loglevel', 'error', '-f', 'lavfi', '-i', $source];
        if ($seconds !== null) {
            array_push($command, '-t', (string) $seconds);
        }
        array_push($command, '-c:a', 'libopus', '-y', $path);
        (new Process($command))->mustRun();

        return $path;
    }

    private function temporaryPath(): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'voice_note_test_');
        $this->temporaryFiles[] = $path;

        return $path;
    }
}
