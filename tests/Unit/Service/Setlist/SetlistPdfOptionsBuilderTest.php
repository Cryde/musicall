<?php declare(strict_types=1);

namespace App\Tests\Unit\Service\Setlist;

use App\Enum\BandSpace\SetlistPdfFont;
use App\Enum\BandSpace\SetlistPdfLayout;
use App\Service\Setlist\SetlistPdfOptionsBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class SetlistPdfOptionsBuilderTest extends TestCase
{
    public function test_no_request_yields_large_layout_default_toggles(): void
    {
        $builder = new SetlistPdfOptionsBuilder();
        $options = $builder->fromRequest(null);

        $this->assertSame(SetlistPdfLayout::Large, $options->layout);
        $this->assertTrue($options->showTempo);
        $this->assertTrue($options->showKey);
        $this->assertTrue($options->showDurations);
        $this->assertFalse($options->showNotes);
        $this->assertFalse($options->showTransitions);
        $this->assertNull($options->font);
        $this->assertSame(SetlistPdfFont::Inter, $options->effectiveFont());
    }

    public function test_large_honors_query_toggles(): void
    {
        $builder = new SetlistPdfOptionsBuilder();
        $request = new Request([
            'layout' => 'large',
            'showTempo' => '0',
            'showKey' => '0',
            'showDurations' => '0',
            'showNotes' => '1',
            'showTransitions' => '1',
        ]);

        $options = $builder->fromRequest($request);

        $this->assertSame(SetlistPdfLayout::Large, $options->layout);
        $this->assertFalse($options->showTempo);
        $this->assertFalse($options->showKey);
        $this->assertFalse($options->showDurations);
        $this->assertTrue($options->showNotes);
        $this->assertTrue($options->showTransitions);
    }

    public function test_compact_forces_all_per_field_toggles_off_even_if_query_sets_them(): void
    {
        $builder = new SetlistPdfOptionsBuilder();
        $request = new Request([
            'layout' => 'compact',
            'showTempo' => '1',
            'showKey' => '1',
            'showDurations' => '1',
            'showNotes' => '1',
            'showTransitions' => '1',
        ]);

        $options = $builder->fromRequest($request);

        $this->assertSame(SetlistPdfLayout::Compact, $options->layout);
        $this->assertFalse($options->showTempo);
        $this->assertFalse($options->showKey);
        $this->assertFalse($options->showDurations);
        $this->assertFalse($options->showNotes);
        $this->assertFalse($options->showTransitions);
    }

    public function test_compact_default_font_is_atkinson_hyperlegible(): void
    {
        $builder = new SetlistPdfOptionsBuilder();
        $options = $builder->fromRequest(new Request(['layout' => 'compact']));

        $this->assertSame(SetlistPdfFont::AtkinsonHyperlegible, $options->effectiveFont());
    }

    public function test_invalid_font_falls_back_to_layout_default_without_error(): void
    {
        $builder = new SetlistPdfOptionsBuilder();
        $options = $builder->fromRequest(new Request(['font' => 'not-a-real-font']));

        $this->assertNull($options->font);
        $this->assertSame(SetlistPdfFont::Inter, $options->effectiveFont());
    }

    public function test_valid_font_value_is_applied(): void
    {
        $builder = new SetlistPdfOptionsBuilder();
        $options = $builder->fromRequest(new Request(['font' => 'source_serif']));

        $this->assertSame(SetlistPdfFont::SourceSerif, $options->font);
        $this->assertSame(SetlistPdfFont::SourceSerif, $options->effectiveFont());
    }

    public function test_invalid_layout_falls_back_to_large(): void
    {
        $builder = new SetlistPdfOptionsBuilder();
        $options = $builder->fromRequest(new Request(['layout' => 'not-a-layout']));

        $this->assertSame(SetlistPdfLayout::Large, $options->layout);
    }

    public function test_fit_to_one_page_defaults_off(): void
    {
        $builder = new SetlistPdfOptionsBuilder();

        $this->assertFalse($builder->fromRequest(null)->fitToOnePage);
        $this->assertFalse($builder->fromRequest(new Request(['layout' => 'large']))->fitToOnePage);
    }

    public function test_fit_to_one_page_is_honored_in_large(): void
    {
        $builder = new SetlistPdfOptionsBuilder();
        $options = $builder->fromRequest(new Request(['layout' => 'large', 'fitToOnePage' => '1']));

        $this->assertTrue($options->fitToOnePage);
    }

    public function test_fit_to_one_page_is_honored_in_compact(): void
    {
        // The flag is orthogonal to the per-field toggles Compact zeroes out.
        $builder = new SetlistPdfOptionsBuilder();
        $options = $builder->fromRequest(new Request(['layout' => 'compact', 'fitToOnePage' => '1']));

        $this->assertSame(SetlistPdfLayout::Compact, $options->layout);
        $this->assertTrue($options->fitToOnePage);
    }
}
