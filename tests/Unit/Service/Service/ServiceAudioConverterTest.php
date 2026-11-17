<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Service;

use App\Service\Audio\AudioTranscoder;
use App\Service\Service\ServiceAudioConverter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Process\Process;

/** Runs the real ffmpeg: every format a studio exports becomes stereo AAC (#1410). */
class ServiceAudioConverterTest extends TestCase
{
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
     * @return iterable<string, array{string, list<string>, string}>
     */
    public static function formats(): iterable
    {
        // The extension, ffmpeg's encoder options, and what libmagic reports for the result.
        yield 'WAV 24-bit' => ['wav', ['-c:a', 'pcm_s24le'], 'audio/x-wav'];
        yield 'AIFF' => ['aiff', [], 'audio/x-aiff'];
        yield 'FLAC' => ['flac', [], 'audio/flac'];
        yield 'MP3' => ['mp3', [], 'audio/mpeg'];
        yield 'M4A' => ['m4a', [], 'audio/x-m4a'];
    }

    /**
     * @param list<string> $encoder
     */
    #[DataProvider('formats')]
    public function test_an_export_becomes_stereo_aac_with_its_duration_and_waveform(string $extension, array $encoder, string $detectedMimeType): void
    {
        $export = new File($this->generate($extension, $encoder));
        $this->assertSame($detectedMimeType, $export->getMimeType());

        $converted = (new ServiceAudioConverter(new AudioTranscoder()))->convert($export, $detectedMimeType);
        $this->temporaryFiles[] = $converted->file->getPathname();

        $this->assertSame(2, $converted->durationSeconds);
        $this->assertSame(['codec_name=aac', 'channels=2'], $this->probe($converted->file->getPathname()));
        $this->assertCount(AudioTranscoder::PEAK_COUNT, $converted->peaks);
    }

    public function test_a_sound_longer_than_the_limit_is_cut_at_it(): void
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'service_audio_') . '.flac';
        $this->temporaryFiles[] = $path;
        (new Process(['ffmpeg', '-nostdin', '-loglevel', 'error', '-f', 'lavfi', '-i', 'anullsrc=r=22050:cl=stereo', '-t', (string) (ServiceAudioConverter::MAX_DURATION_SECONDS + 20), '-y', $path]))->mustRun();

        $converted = (new ServiceAudioConverter(new AudioTranscoder()))->convert(new File($path), 'audio/flac');
        $this->temporaryFiles[] = $converted->file->getPathname();

        $this->assertSame(ServiceAudioConverter::MAX_DURATION_SECONDS, $converted->durationSeconds);
    }

    public function test_a_voice_note_type_is_refused_before_ffmpeg_runs(): void
    {
        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('service.audio.unreadable');
        (new ServiceAudioConverter(new AudioTranscoder()))->convert(new File($this->generate('wav', [])), 'video/webm');
    }

    /** @param list<string> $encoder */
    private function generate(string $extension, array $encoder): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'service_audio_') . '.' . $extension;
        $this->temporaryFiles[] = $path;
        (new Process(['ffmpeg', '-nostdin', '-loglevel', 'error', '-f', 'lavfi', '-i', 'sine=frequency=440:duration=2', '-ac', '2', ...$encoder, '-y', $path]))->mustRun();

        return $path;
    }

    /** @return list<string> */
    private function probe(string $path): array
    {
        $process = new Process(['ffprobe', '-v', 'error', '-select_streams', 'a:0', '-show_entries', 'stream=codec_name,channels', '-of', 'default=noprint_wrappers=1', $path]);
        $process->mustRun();

        return array_values(array_filter(explode("\n", trim($process->getOutput()))));
    }
}
