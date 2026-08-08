<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace\TechRider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\BandSpace\TechRider\TechRiderStagePlotIconResource;
use App\Enum\BandSpace\TechRiderStagePlotIcon;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<TechRiderStagePlotIconResource>
 */
readonly class TechRiderStagePlotIconProvider implements ProviderInterface
{
    /**
     * Straight off the enum, in declaration order. No sorting: the enum groups related icons
     * together already, and reordering here would mean the catalogue and the source disagree
     * about what "first" means.
     *
     * @return TechRiderStagePlotIconResource[]|TechRiderStagePlotIconResource
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|TechRiderStagePlotIconResource
    {
        if (isset($uriVariables['slug'])) {
            $icon = TechRiderStagePlotIcon::tryFrom((string) $uriVariables['slug']);
            if ($icon === null) {
                throw new NotFoundHttpException('Icône introuvable');
            }

            return $this->build($icon);
        }

        return array_map($this->build(...), TechRiderStagePlotIcon::cases());
    }

    private function build(TechRiderStagePlotIcon $icon): TechRiderStagePlotIconResource
    {
        $resource = new TechRiderStagePlotIconResource();
        $resource->slug = $icon->value;
        $resource->label = $icon->label();
        $resource->category = $icon->category()->value;
        $resource->categoryLabel = $icon->category()->label();
        $resource->categoryColour = $icon->category()->hex();
        $resource->imageUrl = $icon->imagePath();

        return $resource;
    }
}
