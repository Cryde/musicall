<?php

declare(strict_types=1);

namespace App\Service\Builder\Service;

use App\ApiResource\Service\SoundEngineering\SoundEngineerPage;
use App\Entity\Service\Service;
use App\Entity\Service\SoundEngineeringDetails;

readonly class SoundEngineerPageBuilder
{
    public function __construct(
        private ServicePageBuilder $pageBuilder,
        private ServiceViewBuilder $serviceViewBuilder,
    ) {
    }

    public function build(Service $service): SoundEngineerPage
    {
        $page = new SoundEngineerPage();
        $this->pageBuilder->fill($page, $service);

        $details = SoundEngineeringDetails::of($service);
        $page->turnaroundDays = $details->turnaroundDays;
        $page->revisionsIncluded = $details->revisionsIncluded;
        $page->formats = $details->formats;
        $page->gear = $details->gear;
        $page->beforeAfter = $this->serviceViewBuilder->beforeAfter($service);

        return $page;
    }
}
