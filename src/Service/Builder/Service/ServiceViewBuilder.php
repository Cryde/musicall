<?php

declare(strict_types=1);

namespace App\Service\Builder\Service;

use App\ApiResource\Service\Search\TeacherSearchMedia;
use App\ApiResource\Service\View\ServiceAudioView;
use App\ApiResource\Service\View\ServiceOfferView;
use App\ApiResource\Service\View\ServicePlaceView;
use App\ApiResource\Service\View\ServiceWorkImageView;
use App\ApiResource\Service\View\ServiceWorkMedia;
use App\ApiResource\Service\View\ServiceWorkView;
use App\Entity\Service\Service;
use App\Entity\Service\ServiceAudio;
use App\Entity\Service\ServiceMedia;
use App\Entity\Service\ServiceOffer;
use App\Entity\Service\ServicePlace;
use App\Entity\Service\ServiceWork;
use App\Entity\Service\ServiceWorkImage;
use App\Enum\Musician\MediaPlatform;
use App\Enum\Service\BeforeAfterSide;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/** The parts every kind's page shows the same way (#1410). */
readonly class ServiceViewBuilder
{
    public function __construct(private UrlGeneratorInterface $urlGenerator)
    {
    }

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

    /** @return list<ServiceWorkView> in the owner's order */
    public function works(Service $service): array
    {
        return array_map(fn (ServiceWork $work): ServiceWorkView => $this->work($work), $service->orderedWorks());
    }

    /**
     * @template T of ServiceWorkView
     *
     * @param T $view
     *
     * @return T
     */
    public function work(ServiceWork $work, ServiceWorkView $view = new ServiceWorkView()): ServiceWorkView
    {
        $view->id = (string) $work->id;
        $view->title = $work->title;
        $view->credit = $work->credit;
        $view->year = $work->year;
        $view->linkUrl = $work->linkUrl;
        $view->media = $this->workMedia($work);
        $view->images = array_map(fn (ServiceWorkImage $image): ServiceWorkImageView => $this->workImage($image), $work->orderedImages());

        return $view;
    }

    /**
     * @template T of ServiceWorkImageView
     *
     * @param T $view
     *
     * @return T
     */
    public function workImage(ServiceWorkImage $image, ServiceWorkImageView $view = new ServiceWorkImageView()): ServiceWorkImageView
    {
        $view->id = (string) $image->id;
        $view->url = $this->urlGenerator->generate('api_service_work_images_get', [
            'serviceId' => $image->work->service->id,
            'workId' => $image->work->id,
            'id' => $image->id,
        ]);

        return $view;
    }

    private function workMedia(ServiceWork $work): ?ServiceWorkMedia
    {
        if (!$work->mediaPlatform instanceof MediaPlatform || $work->mediaUrl === null || $work->mediaEmbedId === null) {
            return null;
        }
        $media = new ServiceWorkMedia();
        $media->platform = $work->mediaPlatform->value;
        $media->url = $work->mediaUrl;
        $media->embedId = $work->mediaEmbedId;
        $media->title = $work->mediaTitle;

        return $media;
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
