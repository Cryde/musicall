<?php declare(strict_types=1);

namespace App\Service\Publication;

use App\Repository\GalleryRepository;
use Symfony\Component\String\Slugger\SluggerInterface;

class GallerySlug
{
    public function __construct(private readonly GalleryRepository $galleryRepository, private readonly SluggerInterface $slugger)
    {
    }

    public function create(string $slugCandidate): string
    {
        $slug = $this->slugger->slug($slugCandidate)->lower();
        $i = 1;
        $initialSlug = $slug;
        while ($this->galleryRepository->count(['slug' => $slug]) > 0) {
            $slug = $initialSlug . '-' . $i++;
        }

        return (string) $slug;
    }
}
