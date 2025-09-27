<?php

namespace App\Fixtures\Attribute;

use App\Fixtures\Factory\Attribute\InstrumentFactory;
use Zenstruck\Foundry\Story;

/** @codeCoverageIgnore */
class InstrumentStory extends Story
{
    const string ATTRIBUTES_INSTRUMENTS = 'attributes_instruments';

    public function build(): void
    {
        $this->addToPool(self::ATTRIBUTES_INSTRUMENTS, InstrumentFactory::new()->asGuitar());
        $this->addToPool(self::ATTRIBUTES_INSTRUMENTS, InstrumentFactory::new()->asUkulele());
        $this->addToPool(self::ATTRIBUTES_INSTRUMENTS, InstrumentFactory::new()->asFlute());
        $this->addToPool(self::ATTRIBUTES_INSTRUMENTS, InstrumentFactory::new()->asSaxophone());
        $this->addToPool(self::ATTRIBUTES_INSTRUMENTS, InstrumentFactory::new()->asTrumpet());
        $this->addToPool(self::ATTRIBUTES_INSTRUMENTS, InstrumentFactory::new()->asDidgeridoo());
        $this->addToPool(self::ATTRIBUTES_INSTRUMENTS, InstrumentFactory::new()->asDjembe());
        $this->addToPool(self::ATTRIBUTES_INSTRUMENTS, InstrumentFactory::new()->asDJ());
        $this->addToPool(self::ATTRIBUTES_INSTRUMENTS, InstrumentFactory::new()->asDoubleBass());
        $this->addToPool(self::ATTRIBUTES_INSTRUMENTS, InstrumentFactory::new()->asViolin());
        $this->addToPool(self::ATTRIBUTES_INSTRUMENTS, InstrumentFactory::new()->asCello());
        $this->addToPool(self::ATTRIBUTES_INSTRUMENTS, InstrumentFactory::new()->asPiano());
        $this->addToPool(self::ATTRIBUTES_INSTRUMENTS, InstrumentFactory::new()->asBass());
        $this->addToPool(self::ATTRIBUTES_INSTRUMENTS, InstrumentFactory::new()->asDrums());
        $this->addToPool(self::ATTRIBUTES_INSTRUMENTS, InstrumentFactory::new()->asVocals());
        $this->addToPool(self::ATTRIBUTES_INSTRUMENTS, InstrumentFactory::new()->asKeyboard());
        $this->addToPool(self::ATTRIBUTES_INSTRUMENTS, InstrumentFactory::new()->asBanjo());
    }
}
