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
     * @var int
     */
    private $instrument;
    /**
     * @var int[]
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

    /**
     * @return int
     */
    public function getInstrument(): int
    {
        return $this->instrument;
    }

    /**
     * @param int $instrument
     *
     * @return Musician
     */
    public function setInstrument(int $instrument): Musician
    {
        $this->instrument = $instrument;

        return $this;
    }

    /**
     * @return int[]
     */
    public function getStyles(): array
    {
        return $this->styles;
    }

    /**
     * @param int[] $styles
     *
     * @return Musician
     */
    public function setStyles(array $styles): Musician
    {
        $this->styles = $styles;

        return $this;
    }

    /**
     * @return float|null
     */
    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    /**
     * @param float|null $latitude
     *
     * @return Musician
     */
    public function setLatitude(?float $latitude): Musician
    {
        $this->latitude = $latitude;

        return $this;
    }

    /**
     * @return float|null
     */
    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    /**
     * @param float|null $longitude
     *
     * @return Musician
     */
    public function setLongitude(?float $longitude): Musician
    {
        $this->longitude = $longitude;

        return $this;
    }
}
