<?php

namespace App\Service\Publication;

use App\Repository\GalleryRepository;
use Symfony\Component\String\Slugger\SluggerInterface;

class GallerySlug
{
    private GalleryRepository $galleryRepository;
    private SluggerInterface $slugger;

    public function __construct(GalleryRepository $galleryRepository, SluggerInterface $slugger)
    {
        $this->galleryRepository = $galleryRepository;
        $this->slugger = $slugger;
    }

    public function create(string $slugCandidate): string
    {
        $slug = $this->slugger->slug($slugCandidate)->lower();
        $i = 1;
        $initialSlug = $slug;
        while ($this->galleryRepository->count(['slug' => $slug]) > 0) {
            $slug = $initialSlug . '-' . $i++;
        }

        return $slug;
    }
}
