<?php

namespace App\Fixtures\Factory\Attribute;

use Zenstruck\Foundry\Factory;
use App\Entity\Attribute\Style;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/** @codeCoverageIgnore */
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

    public function asMusiqueLatine(): Factory
    {
        return $this->with(['name' => 'Musique Latine', 'slug' => 'musique-latine']);
    }

    public function asRnB(): Factory
    {
        return $this->with(['name' => 'RnB', 'slug' => 'rnb']);
    }

    public function asPop(): Factory
    {
        return $this->with(['name' => 'Pop', 'slug' => 'pop']);
    }

    public function asToutStyle(): Factory
    {
        return $this->with(['name' => 'Tout style', 'slug' => 'tout-style']);
    }

    public function asReggae(): Factory
    {
        return $this->with(['name' => 'Reggae', 'slug' => 'reggae']);
    }

    public function asMusiqueduMonde(): Factory
    {
        return $this->with(['name' => 'Musique du monde', 'slug' => 'musique-du-monde']);
    }

    public function asFunk(): Factory
    {
        return $this->with(['name' => 'Funk', 'slug' => 'funk']);
    }

    public function asFusion(): Factory
    {
        return $this->with(['name' => 'Fusion', 'slug' => 'fusion']);
    }

    public function asGothique(): Factory
    {
        return $this->with(['name' => 'Gothique', 'slug' => 'gothique']);
    }

    public function asSka(): Factory
    {
        return $this->with(['name' => 'Ska', 'slug' => 'ska']);
    }

    public function asExperimental(): Factory
    {
        return $this->with(['name' => 'Expérimental', 'slug' => 'experimental']);
    }

    public function asElectro(): Factory
    {
        return $this->with(['name' => 'Electro', 'slug' => 'electro']);
    }

    public function asTrance(): Factory
    {
        return $this->with(['name' => 'Trance', 'slug' => 'trance']);
    }

    public function asHouse(): Factory
    {
        return $this->with(['name' => 'House', 'slug' => 'house']);
    }

    public function asAcidTechno(): Factory
    {
        return $this->with(['name' => 'Acid Techno', 'slug' => 'acid-techno']);
    }

    public function asAcoustique(): Factory
    {
        return $this->with(['name' => 'Acoustique', 'slug' => 'acoustique']);
    }

    public function asAfrobeat(): Factory
    {
        return $this->with(['name' => 'Afrobeat', 'slug' => 'afrobeat']);
    }

    public function asBlues(): Factory
    {
        return $this->with(['name' => 'Blues', 'slug' => 'blues']);
    }

    public function asBoogieWoogie(): Factory
    {
        return $this->with(['name' => 'Boogie Woogie', 'slug' => 'boogie-woogie']);
    }

    public function asBossaNova(): Factory
    {
        return $this->with(['name' => 'Bossa Nova', 'slug' => 'bossa-nova']);
    }

    public function asBritPop(): Factory
    {
        return $this->with(['name' => 'Brit Pop', 'slug' => 'brit-pop']);
    }

    public function asChansonFrancaise(): Factory
    {
        return $this->with(['name' => 'Chanson Française', 'slug' => 'chanson-francaise']);
    }

    public function asCountry(): Factory
    {
        return $this->with(['name' => 'Country', 'slug' => 'country']);
    }

    public function asCoverBand(): Factory
    {
        return $this->with(['name' => 'Cover Band', 'slug' => 'cover-band']);
    }

    public function asCrabcore(): Factory
    {
        return $this->with(['name' => 'Crabcore', 'slug' => 'crabcore']);
    }

    public function asDeathMetal(): Factory
    {
        return $this->with(['name' => 'Death Metal', 'slug' => 'death-metal']);
    }

    public function asDeathcore(): Factory
    {
        return $this->with(['name' => 'Deathcore', 'slug' => 'deathcore']);
    }

    public function asDjent(): Factory
    {
        return $this->with(['name' => 'Djent', 'slug' => 'djent']);
    }

    public function asDoom(): Factory
    {
        return $this->with(['name' => 'Doom', 'slug' => 'doom']);
    }

    public function asDowntempo(): Factory
    {
        return $this->with(['name' => 'Downtempo', 'slug' => 'downtempo']);
    }

    public function asDrumandBass(): Factory
    {
        return $this->with(['name' => 'Drum and Bass', 'slug' => 'drum-and-bass']);
    }

    public function asDrumstep(): Factory
    {
        return $this->with(['name' => 'Drumstep', 'slug' => 'drumstep']);
    }

    public function asDubstep(): Factory
    {
        return $this->with(['name' => 'Dubstep', 'slug' => 'dubstep']);
    }

    public function asEasycore(): Factory
    {
        return $this->with(['name' => 'Easycore', 'slug' => 'easycore']);
    }

    public function asEDM(): Factory
    {
        return $this->with(['name' => 'EDM', 'slug' => 'edm']);
    }

    public function asEmo(): Factory
    {
        return $this->with(['name' => 'Emo', 'slug' => 'emo']);
    }

    public function asFanfare(): Factory
    {
        return $this->with(['name' => 'Fanfare', 'slug' => 'fanfare']);
    }

    public function asFlamenco(): Factory
    {
        return $this->with(['name' => 'Flamenco', 'slug' => 'flamenco']);
    }

    public function asFolk(): Factory
    {
        return $this->with(['name' => 'Folk', 'slug' => 'folk']);
    }

    public function asFolkMetal(): Factory
    {
        return $this->with(['name' => 'Folk Metal', 'slug' => 'folk-metal']);
    }

    public function asFunkRock(): Factory
    {
        return $this->with(['name' => 'Funk-Rock', 'slug' => 'funk-rock']);
    }

    public function asGarageRock(): Factory
    {
        return $this->with(['name' => 'Garage Rock', 'slug' => 'garage-rock']);
    }

    public function asGlamMetal(): Factory
    {
        return $this->with(['name' => 'Glam Metal', 'slug' => 'glam-metal']);
    }

    public function asGospel(): Factory
    {
        return $this->with(['name' => 'Gospel', 'slug' => 'gospel']);
    }

    public function asGrindcore(): Factory
    {
        return $this->with(['name' => 'Grindcore', 'slug' => 'grindcore']);
    }

    public function asGrooveMetal(): Factory
    {
        return $this->with(['name' => 'Groove Metal', 'slug' => 'groove-metal']);
    }

    public function asGrunge(): Factory
    {
        return $this->with(['name' => 'Grunge', 'slug' => 'grunge']);
    }

    public function asHardRock(): Factory
    {
        return $this->with(['name' => 'Hard-Rock', 'slug' => 'hard-rock']);
    }

    public function asHardcore(): Factory
    {
        return $this->with(['name' => 'Hardcore', 'slug' => 'hardcore']);
    }

    public function asHeavyMetal(): Factory
    {
        return $this->with(['name' => 'Heavy Metal', 'slug' => 'heavy-metal']);
    }

    public function asHipHop(): Factory
    {
        return $this->with(['name' => 'Hip-Hop', 'slug' => 'hip-hop']);
    }

    public function asHorrorPunk(): Factory
    {
        return $this->with(['name' => 'Horror Punk', 'slug' => 'horror-punk']);
    }

    public function asIndiePop(): Factory
    {
        return $this->with(['name' => 'Indie Pop', 'slug' => 'indie-pop']);
    }

    public function asInstrumental(): Factory
    {
        return $this->with(['name' => 'Instrumental', 'slug' => 'instrumental']);
    }

    public function asJPop(): Factory
    {
        return $this->with(['name' => 'J-Pop', 'slug' => 'j-pop']);
    }

    public function asJazz(): Factory
    {
        return $this->with(['name' => 'Jazz', 'slug' => 'jazz']);
    }

    public function asJazzFusion(): Factory
    {
        return $this->with(['name' => 'Jazz Fusion', 'slug' => 'jazz-fusion']);
    }

    public function asJumpstyle(): Factory
    {
        return $this->with(['name' => 'Jumpstyle', 'slug' => 'jumpstyle']);
    }

    public function asKPop(): Factory
    {
        return $this->with(['name' => 'K-Pop', 'slug' => 'k-pop']);
    }

    public function asMathcore(): Factory
    {
        return $this->with(['name' => 'Mathcore', 'slug' => 'mathcore']);
    }

    public function asMetal(): Factory
    {
        return $this->with(['name' => 'Métal', 'slug' => 'metal']);
    }

    public function asMetalCeltique(): Factory
    {
        return $this->with(['name' => 'Metal Celtique', 'slug' => 'metal-celtique']);
    }

    public function asMetalIndustriel(): Factory
    {
        return $this->with(['name' => 'Metal Industriel', 'slug' => 'metal-industriel']);
    }

    public function asMetalProgressif(): Factory
    {
        return $this->with(['name' => 'Metal Progressif', 'slug' => 'metal-progressif']);
    }

    public function asMetalSymphonique(): Factory
    {
        return $this->with(['name' => 'Metal Symphonique', 'slug' => 'metal-symphonique']);
    }

    public function asMetalcore(): Factory
    {
        return $this->with(['name' => 'Metalcore', 'slug' => 'metalcore']);
    }

    public function asMusiqueClassique(): Factory
    {
        return $this->with(['name' => 'Musique Classique', 'slug' => 'musique-classique']);
    }

    public function asNewWave(): Factory
    {
        return $this->with(['name' => 'New Wave', 'slug' => 'new-wave']);
    }

    public function asNuMetal(): Factory
    {
        return $this->with(['name' => 'Nu Metal', 'slug' => 'nu-metal']);
    }

    public function asOi(): Factory
    {
        return $this->with(['name' => 'Oï', 'slug' => 'oi']);
    }

    public function asPagan(): Factory
    {
        return $this->with(['name' => 'Pagan', 'slug' => 'pagan']);
    }

    public function asPopPunk(): Factory
    {
        return $this->with(['name' => 'Pop-Punk', 'slug' => 'pop-punk']);
    }

    public function asPostHardcore(): Factory
    {
        return $this->with(['name' => 'Post-Hardcore', 'slug' => 'post-hardcore']);
    }

    public function asPowerMetal(): Factory
    {
        return $this->with(['name' => 'Power Metal', 'slug' => 'power-metal']);
    }

    public function asPunk(): Factory
    {
        return $this->with(['name' => 'Punk', 'slug' => 'punk']);
    }

    public function asPunkRock(): Factory
    {
        return $this->with(['name' => 'Punk Rock', 'slug' => 'punk-rock']);
    }

    public function asRnBVariant(): Factory
    {
        return $this->with(['name' => 'R\'n\'B', 'slug' => 'rnb-variant']);
    }

    public function asRai(): Factory
    {
        return $this->with(['name' => 'Raï', 'slug' => 'rai']);
    }

    public function asRap(): Factory
    {
        return $this->with(['name' => 'Rap', 'slug' => 'rap']);
    }

    public function asRock(): Factory
    {
        return $this->with(['name' => 'Rock', 'slug' => 'rock']);
    }

    public function asRockAlternatif(): Factory
    {
        return $this->with(['name' => 'Rock Alternatif', 'slug' => 'rock-alternatif']);
    }

    public function asRockProgressif(): Factory
    {
        return $this->with(['name' => 'Rock Progressif', 'slug' => 'rock-progressif']);
    }

    public function asRootsRock(): Factory
    {
        return $this->with(['name' => 'Roots Rock', 'slug' => 'roots-rock']);
    }

    public function asSamba(): Factory
    {
        return $this->with(['name' => 'Samba', 'slug' => 'samba']);
    }

    public function asScreamo(): Factory
    {
        return $this->with(['name' => 'Screamo', 'slug' => 'screamo']);
    }

    public function asSkaPunk(): Factory
    {
        return $this->with(['name' => 'Ska Punk', 'slug' => 'ska-punk']);
    }

    public function asSlamDeathcore(): Factory
    {
        return $this->with(['name' => 'Slam Deathcore', 'slug' => 'slam-deathcore']);
    }

    public function asSludge(): Factory
    {
        return $this->with(['name' => 'Sludge', 'slug' => 'sludge']);
    }

    public function asSoul(): Factory
    {
        return $this->with(['name' => 'Soul', 'slug' => 'soul']);
    }

    public function asSpeedMetal(): Factory
    {
        return $this->with(['name' => 'Speed Metal', 'slug' => 'speed-metal']);
    }

    public function asStoner(): Factory
    {
        return $this->with(['name' => 'Stoner', 'slug' => 'stoner']);
    }

    public function asSwing(): Factory
    {
        return $this->with(['name' => 'Swing', 'slug' => 'swing']);
    }

    public function asTechno(): Factory
    {
        return $this->with(['name' => 'Techno', 'slug' => 'techno']);
    }

    public function asThrashMetal(): Factory
    {
        return $this->with(['name' => 'Thrash Metal', 'slug' => 'thrash-metal']);
    }

    public function asTropicalHouse(): Factory
    {
        return $this->with(['name' => 'Tropical House', 'slug' => 'tropical-house']);
    }

    public function asVariete(): Factory
    {
        return $this->with(['name' => 'Variété', 'slug' => 'variete']);
    }

    public function asVikingMetal(): Factory
    {
        return $this->with(['name' => 'Viking Metal', 'slug' => 'viking-metal']);
    }

    public function asVisualKei(): Factory
    {
        return $this->with(['name' => 'Visual Kei', 'slug' => 'visual-kei']);
    }

    public function asMusiqueOrientale(): Factory
    {
        return $this->with(['name' => 'Musique Orientale', 'slug' => 'musique-orientale']);
    }

    public static function class(): string
    {
        return Style::class;
    }
}
