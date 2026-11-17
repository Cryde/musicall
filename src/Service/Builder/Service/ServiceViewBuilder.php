<?php

declare(strict_types=1);

namespace App\Service\Builder\Service;

use App\ApiResource\Service\Search\TeacherSearchMedia;
use App\ApiResource\Service\View\ServiceAudioView;
use App\ApiResource\Service\View\ServiceOfferView;
use App\ApiResource\Service\View\ServicePlaceView;
use App\Entity\Service\Service;
use App\Entity\Service\ServiceAudio;
use App\Entity\Service\ServiceMedia;
use App\Entity\Service\ServiceOffer;
use App\Entity\Service\ServicePlace;
use App\Enum\Service\BeforeAfterSide;

/** The parts every kind's page shows the same way (#1410). */
readonly class ServiceViewBuilder
{
    /** @return list<ServicePlaceView> */
    public function places(Service $service): array
    {
        return array_values(array_map(static function (ServicePlace $place): ServicePlaceView {
            $view = new ServicePlaceView();
            $view->type = $place->type->value;
            $view->city = $place->city;
            $view->radius = $place->radius;

            return $view;
        }, $service->places->toArray()));
    }

    /** @return list<ServiceOfferView> the cheapest first, as « Formules » lists them */
    public function offers(Service $service): array
    {
        return array_map(static function (ServiceOffer $offer): ServiceOfferView {
            $view = new ServiceOfferView();
            $view->id = (string) $offer->id;
            $view->unit = $offer->unit->value;
            $view->title = $offer->title;
            $view->description = $offer->description;
            $view->price = $offer->price;

            return $view;
        }, $service->offersByPrice());
    }

    /** @return list<ServiceAudioView> before first */
    public function beforeAfter(Service $service): array
    {
        $views = [];
        foreach (BeforeAfterSide::cases() as $side) {
            if (($audio = $service->audioOn($side)) instanceof ServiceAudio) {
                $views[] = $this->audio($audio);
            }
        }

        return $views;
    }

    /**
     * @template T of ServiceAudioView
     *
     * @param T $view
     *
     * @return T
     */
    public function audio(ServiceAudio $audio, ServiceAudioView $view = new ServiceAudioView()): ServiceAudioView
    {
        $view->audioId = (string) $audio->id;
        $view->side = $audio->side->value;
        $view->title = $audio->title;
        $view->credit = $audio->credit;
        $view->durationSeconds = $audio->durationSeconds;
        $view->peaks = $audio->peaks;

        return $view;
    }

    /** The first media of a search card, which « Écouter » plays without leaving the list. */
    public function firstMedia(Service $service): ?TeacherSearchMedia
    {
        // Picked by position: the mapping's order is not applied to a collection already loaded.
        $first = null;
        foreach ($service->media as $media) {
            if (!$first instanceof ServiceMedia || $media->position < $first->position) {
                $first = $media;
            }
        }
        if (!$first instanceof ServiceMedia) {
            return null;
        }

        $dto = new TeacherSearchMedia();
        $dto->platform = $first->platform->value;
        $dto->embedId = $first->embedId;
        $dto->title = $first->title;

        return $dto;
    }
}
