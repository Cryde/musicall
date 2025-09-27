<?php

namespace App\Fixtures\Attribute;

use App\Fixtures\Factory\Attribute\StyleFactory;
use Zenstruck\Foundry\Story;

/** @codeCoverageIgnore */
class StyleStory extends Story
{
    public const string ATTRIBUTES_STYLES = 'attributes_styles';

    public function build(): void
    {
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asMusiqueLatine());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asRnB());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asPop());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asToutStyle());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asReggae());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asMusiqueduMonde());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asFunk());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asFusion());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asGothique());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asSka());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asExperimental());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asElectro());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asTrance());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asHouse());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asAcidTechno());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asAcoustique());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asAfrobeat());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asBlues());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asBoogieWoogie());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asBossaNova());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asBritPop());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asChansonFrancaise());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asCountry());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asCoverBand());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asCrabcore());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asDeathMetal());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asDeathcore());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asDjent());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asDoom());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asDowntempo());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asDrumandBass());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asDrumstep());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asDubstep());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asEasycore());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asEDM());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asEmo());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asFanfare());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asFlamenco());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asFolk());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asFolkMetal());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asFunkRock());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asGarageRock());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asGlamMetal());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asGospel());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asGrindcore());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asGrooveMetal());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asGrunge());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asHardRock());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asHardcore());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asHeavyMetal());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asHipHop());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asHorrorPunk());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asIndiePop());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asInstrumental());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asJPop());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asJazz());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asJazzFusion());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asJumpstyle());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asKPop());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asMathcore());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asMetal());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asMetalCeltique());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asMetalIndustriel());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asMetalProgressif());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asMetalSymphonique());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asMetalcore());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asMusiqueClassique());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asNewWave());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asNuMetal());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asOi());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asPagan());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asPopPunk());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asPostHardcore());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asPowerMetal());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asPunk());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asPunkRock());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asRnBVariant());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asRai());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asRap());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asRock());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asRockAlternatif());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asRockProgressif());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asRootsRock());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asSamba());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asScreamo());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asSkaPunk());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asSlamDeathcore());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asSludge());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asSoul());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asSpeedMetal());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asStoner());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asSwing());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asTechno());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asThrashMetal());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asTropicalHouse());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asVariete());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asVikingMetal());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asVisualKei());
        $this->addToPool(self::ATTRIBUTES_STYLES, StyleFactory::new()->asMusiqueOrientale());
    }
}
