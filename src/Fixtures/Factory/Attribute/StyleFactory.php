<?php declare(strict_types=1);

namespace App\Fixtures\Factory\Attribute;

use Zenstruck\Foundry\Factory;
use App\Entity\Attribute\Style;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @codeCoverageIgnore
 *
 * @extends PersistentProxyObjectFactory<Style>
 */
final class StyleFactory extends PersistentProxyObjectFactory
{
    protected function defaults(): array
    {
        return [
            'creationDatetime' => self::faker()->dateTime(),
            'name' => self::faker()->text(255),
            'slug' => self::faker()->text(255),
        ];
    }

    public function asMusiqueLatine(): self
    {
        return $this->with(['name' => 'Musique Latine', 'slug' => 'musique-latine']);
    }

    public function asRnB(): self
    {
        return $this->with(['name' => 'RnB', 'slug' => 'rnb']);
    }

    public function asPop(): self
    {
        return $this->with(['name' => 'Pop', 'slug' => 'pop']);
    }

    public function asToutStyle(): self
    {
        return $this->with(['name' => 'Tout style', 'slug' => 'tout-style']);
    }

    public function asReggae(): self
    {
        return $this->with(['name' => 'Reggae', 'slug' => 'reggae']);
    }

    public function asMusiqueduMonde(): self
    {
        return $this->with(['name' => 'Musique du monde', 'slug' => 'musique-du-monde']);
    }

    public function asFunk(): self
    {
        return $this->with(['name' => 'Funk', 'slug' => 'funk']);
    }

    public function asFusion(): self
    {
        return $this->with(['name' => 'Fusion', 'slug' => 'fusion']);
    }

    public function asGothique(): self
    {
        return $this->with(['name' => 'Gothique', 'slug' => 'gothique']);
    }

    public function asSka(): self
    {
        return $this->with(['name' => 'Ska', 'slug' => 'ska']);
    }

    public function asExperimental(): self
    {
        return $this->with(['name' => 'Expérimental', 'slug' => 'experimental']);
    }

    public function asElectro(): self
    {
        return $this->with(['name' => 'Electro', 'slug' => 'electro']);
    }

    public function asTrance(): self
    {
        return $this->with(['name' => 'Trance', 'slug' => 'trance']);
    }

    public function asHouse(): self
    {
        return $this->with(['name' => 'House', 'slug' => 'house']);
    }

    public function asAcidTechno(): self
    {
        return $this->with(['name' => 'Acid Techno', 'slug' => 'acid-techno']);
    }

    public function asAcoustique(): self
    {
        return $this->with(['name' => 'Acoustique', 'slug' => 'acoustique']);
    }

    public function asAfrobeat(): self
    {
        return $this->with(['name' => 'Afrobeat', 'slug' => 'afrobeat']);
    }

    public function asBlues(): self
    {
        return $this->with(['name' => 'Blues', 'slug' => 'blues']);
    }

    public function asBoogieWoogie(): self
    {
        return $this->with(['name' => 'Boogie Woogie', 'slug' => 'boogie-woogie']);
    }

    public function asBossaNova(): self
    {
        return $this->with(['name' => 'Bossa Nova', 'slug' => 'bossa-nova']);
    }

    public function asBritPop(): self
    {
        return $this->with(['name' => 'Brit Pop', 'slug' => 'brit-pop']);
    }

    public function asChansonFrancaise(): self
    {
        return $this->with(['name' => 'Chanson Française', 'slug' => 'chanson-francaise']);
    }

    public function asCountry(): self
    {
        return $this->with(['name' => 'Country', 'slug' => 'country']);
    }

    public function asCoverBand(): self
    {
        return $this->with(['name' => 'Cover Band', 'slug' => 'cover-band']);
    }

    public function asCrabcore(): self
    {
        return $this->with(['name' => 'Crabcore', 'slug' => 'crabcore']);
    }

    public function asDeathMetal(): self
    {
        return $this->with(['name' => 'Death Metal', 'slug' => 'death-metal']);
    }

    public function asDeathcore(): self
    {
        return $this->with(['name' => 'Deathcore', 'slug' => 'deathcore']);
    }

    public function asDjent(): self
    {
        return $this->with(['name' => 'Djent', 'slug' => 'djent']);
    }

    public function asDoom(): self
    {
        return $this->with(['name' => 'Doom', 'slug' => 'doom']);
    }

    public function asDowntempo(): self
    {
        return $this->with(['name' => 'Downtempo', 'slug' => 'downtempo']);
    }

    public function asDrumandBass(): self
    {
        return $this->with(['name' => 'Drum and Bass', 'slug' => 'drum-and-bass']);
    }

    public function asDrumstep(): self
    {
        return $this->with(['name' => 'Drumstep', 'slug' => 'drumstep']);
    }

    public function asDubstep(): self
    {
        return $this->with(['name' => 'Dubstep', 'slug' => 'dubstep']);
    }

    public function asEasycore(): self
    {
        return $this->with(['name' => 'Easycore', 'slug' => 'easycore']);
    }

    public function asEDM(): self
    {
        return $this->with(['name' => 'EDM', 'slug' => 'edm']);
    }

    public function asEmo(): self
    {
        return $this->with(['name' => 'Emo', 'slug' => 'emo']);
    }

    public function asFanfare(): self
    {
        return $this->with(['name' => 'Fanfare', 'slug' => 'fanfare']);
    }

    public function asFlamenco(): self
    {
        return $this->with(['name' => 'Flamenco', 'slug' => 'flamenco']);
    }

    public function asFolk(): self
    {
        return $this->with(['name' => 'Folk', 'slug' => 'folk']);
    }

    public function asFolkMetal(): self
    {
        return $this->with(['name' => 'Folk Metal', 'slug' => 'folk-metal']);
    }

    public function asFunkRock(): self
    {
        return $this->with(['name' => 'Funk-Rock', 'slug' => 'funk-rock']);
    }

    public function asGarageRock(): self
    {
        return $this->with(['name' => 'Garage Rock', 'slug' => 'garage-rock']);
    }

    public function asGlamMetal(): self
    {
        return $this->with(['name' => 'Glam Metal', 'slug' => 'glam-metal']);
    }

    public function asGospel(): self
    {
        return $this->with(['name' => 'Gospel', 'slug' => 'gospel']);
    }

    public function asGrindcore(): self
    {
        return $this->with(['name' => 'Grindcore', 'slug' => 'grindcore']);
    }

    public function asGrooveMetal(): self
    {
        return $this->with(['name' => 'Groove Metal', 'slug' => 'groove-metal']);
    }

    public function asGrunge(): self
    {
        return $this->with(['name' => 'Grunge', 'slug' => 'grunge']);
    }

    public function asHardRock(): self
    {
        return $this->with(['name' => 'Hard-Rock', 'slug' => 'hard-rock']);
    }

    public function asHardcore(): self
    {
        return $this->with(['name' => 'Hardcore', 'slug' => 'hardcore']);
    }

    public function asHeavyMetal(): self
    {
        return $this->with(['name' => 'Heavy Metal', 'slug' => 'heavy-metal']);
    }

    public function asHipHop(): self
    {
        return $this->with(['name' => 'Hip-Hop', 'slug' => 'hip-hop']);
    }

    public function asHorrorPunk(): self
    {
        return $this->with(['name' => 'Horror Punk', 'slug' => 'horror-punk']);
    }

    public function asIndiePop(): self
    {
        return $this->with(['name' => 'Indie Pop', 'slug' => 'indie-pop']);
    }

    public function asInstrumental(): self
    {
        return $this->with(['name' => 'Instrumental', 'slug' => 'instrumental']);
    }

    public function asJPop(): self
    {
        return $this->with(['name' => 'J-Pop', 'slug' => 'j-pop']);
    }

    public function asJazz(): self
    {
        return $this->with(['name' => 'Jazz', 'slug' => 'jazz']);
    }

    public function asJazzFusion(): self
    {
        return $this->with(['name' => 'Jazz Fusion', 'slug' => 'jazz-fusion']);
    }

    public function asJumpstyle(): self
    {
        return $this->with(['name' => 'Jumpstyle', 'slug' => 'jumpstyle']);
    }

    public function asKPop(): self
    {
        return $this->with(['name' => 'K-Pop', 'slug' => 'k-pop']);
    }

    public function asMathcore(): self
    {
        return $this->with(['name' => 'Mathcore', 'slug' => 'mathcore']);
    }

    public function asMetal(): self
    {
        return $this->with(['name' => 'Métal', 'slug' => 'metal']);
    }

    public function asMetalCeltique(): self
    {
        return $this->with(['name' => 'Metal Celtique', 'slug' => 'metal-celtique']);
    }

    public function asMetalIndustriel(): self
    {
        return $this->with(['name' => 'Metal Industriel', 'slug' => 'metal-industriel']);
    }

    public function asMetalProgressif(): self
    {
        return $this->with(['name' => 'Metal Progressif', 'slug' => 'metal-progressif']);
    }

    public function asMetalSymphonique(): self
    {
        return $this->with(['name' => 'Metal Symphonique', 'slug' => 'metal-symphonique']);
    }

    public function asMetalcore(): self
    {
        return $this->with(['name' => 'Metalcore', 'slug' => 'metalcore']);
    }

    public function asMusiqueClassique(): self
    {
        return $this->with(['name' => 'Musique Classique', 'slug' => 'musique-classique']);
    }

    public function asNewWave(): self
    {
        return $this->with(['name' => 'New Wave', 'slug' => 'new-wave']);
    }

    public function asNuMetal(): self
    {
        return $this->with(['name' => 'Nu Metal', 'slug' => 'nu-metal']);
    }

    public function asOi(): self
    {
        return $this->with(['name' => 'Oï', 'slug' => 'oi']);
    }

    public function asPagan(): self
    {
        return $this->with(['name' => 'Pagan', 'slug' => 'pagan']);
    }

    public function asPopPunk(): self
    {
        return $this->with(['name' => 'Pop-Punk', 'slug' => 'pop-punk']);
    }

    public function asPostHardcore(): self
    {
        return $this->with(['name' => 'Post-Hardcore', 'slug' => 'post-hardcore']);
    }

    public function asPowerMetal(): self
    {
        return $this->with(['name' => 'Power Metal', 'slug' => 'power-metal']);
    }

    public function asPunk(): self
    {
        return $this->with(['name' => 'Punk', 'slug' => 'punk']);
    }

    public function asPunkRock(): self
    {
        return $this->with(['name' => 'Punk Rock', 'slug' => 'punk-rock']);
    }

    public function asRnBVariant(): self
    {
        return $this->with(['name' => 'R\'n\'B', 'slug' => 'rnb-variant']);
    }

    public function asRai(): self
    {
        return $this->with(['name' => 'Raï', 'slug' => 'rai']);
    }

    public function asRap(): self
    {
        return $this->with(['name' => 'Rap', 'slug' => 'rap']);
    }

    public function asRock(): self
    {
        return $this->with(['name' => 'Rock', 'slug' => 'rock']);
    }

    public function asRockAlternatif(): self
    {
        return $this->with(['name' => 'Rock Alternatif', 'slug' => 'rock-alternatif']);
    }

    public function asRockProgressif(): self
    {
        return $this->with(['name' => 'Rock Progressif', 'slug' => 'rock-progressif']);
    }

    public function asRootsRock(): self
    {
        return $this->with(['name' => 'Roots Rock', 'slug' => 'roots-rock']);
    }

    public function asSamba(): self
    {
        return $this->with(['name' => 'Samba', 'slug' => 'samba']);
    }

    public function asScreamo(): self
    {
        return $this->with(['name' => 'Screamo', 'slug' => 'screamo']);
    }

    public function asSkaPunk(): self
    {
        return $this->with(['name' => 'Ska Punk', 'slug' => 'ska-punk']);
    }

    public function asSlamDeathcore(): self
    {
        return $this->with(['name' => 'Slam Deathcore', 'slug' => 'slam-deathcore']);
    }

    public function asSludge(): self
    {
        return $this->with(['name' => 'Sludge', 'slug' => 'sludge']);
    }

    public function asSoul(): self
    {
        return $this->with(['name' => 'Soul', 'slug' => 'soul']);
    }

    public function asSpeedMetal(): self
    {
        return $this->with(['name' => 'Speed Metal', 'slug' => 'speed-metal']);
    }

    public function asStoner(): self
    {
        return $this->with(['name' => 'Stoner', 'slug' => 'stoner']);
    }

    public function asSwing(): self
    {
        return $this->with(['name' => 'Swing', 'slug' => 'swing']);
    }

    public function asTechno(): self
    {
        return $this->with(['name' => 'Techno', 'slug' => 'techno']);
    }

    public function asThrashMetal(): self
    {
        return $this->with(['name' => 'Thrash Metal', 'slug' => 'thrash-metal']);
    }

    public function asTropicalHouse(): self
    {
        return $this->with(['name' => 'Tropical House', 'slug' => 'tropical-house']);
    }

    public function asVariete(): self
    {
        return $this->with(['name' => 'Variété', 'slug' => 'variete']);
    }

    public function asVikingMetal(): self
    {
        return $this->with(['name' => 'Viking Metal', 'slug' => 'viking-metal']);
    }

    public function asVisualKei(): self
    {
        return $this->with(['name' => 'Visual Kei', 'slug' => 'visual-kei']);
    }

    public function asMusiqueOrientale(): self
    {
        return $this->with(['name' => 'Musique Orientale', 'slug' => 'musique-orientale']);
    }

    public static function class(): string
    {
        return Style::class;
    }
}
