<?php

namespace App\Model\Search;

use App\Entity\Musician\MusicianAnnounce;
use Symfony\Component\Validator\Constraints as Assert;

class Musician
{
    /**
     * @var int
     * @Assert\Choice(choices={
     *     MusicianAnnounce::TYPE_MUSICIAN: MusicianAnnounce::TYPE_MUSICIAN,
     *     MusicianAnnounce::TYPE_BAND: MusicianAnnounce::TYPE_BAND
     *  })
     */
    private $type;
    /**
     * @var string
     */
    private $instrument;
    /**
     * @var string[]
     */
    private $styles;
    /**
     * @var float|null
     */
    private $latitude;
    /**
     * @var float|null
     */
    private $longitude;

    /**
     * @return int
     */
    public function getType(): int
    {
        return $this->type;
    }

    /**
     * @param int $type
     *
     * @return Musician
     */
    public function setType(int $type): Musician
    {
        $this->type = $type;

        return $this;
    }

    public function getInstrument(): string
    {
        return $this->instrument;
    }

    public function setInstrument(string $instrument): Musician
    {
        $this->instrument = $instrument;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getStyles(): array
    {
        return $this->styles;
    }

    /**
     * @param string[] $styles
     *
     * @return Musician
     */
    public function setStyles(array $styles): Musician
    {
        $this->styles = $styles;

        return $this;
    }

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function setLatitude(?float $latitude): Musician
    {
        $this->latitude = $latitude;

        return $this;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function setLongitude(?float $longitude): Musician
    {
        $this->longitude = $longitude;

        return $this;
    }
}
