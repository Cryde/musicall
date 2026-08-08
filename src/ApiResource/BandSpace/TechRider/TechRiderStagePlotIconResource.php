<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\TechRider;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\BandSpace\TechRider\TechRiderStagePlotIconProvider;

/**
 * The stage plot icon catalogue.
 *
 * Not band space scoped, because it is static application data rather than anybody's content, and
 * gated only on being logged in.
 *
 * It exists so the picker has one source of truth. Shipping the same list as a JS constant would
 * be two lists that drift the first time an icon is added, and unlike the colour palette there is
 * no synchronous client need that would justify the copy: the picker is a screen the user opens,
 * so it can wait for a request.
 */
#[ApiResource(
    shortName: 'TechRiderStagePlotIcon',
    operations: [
        // Declared so the @id each entry carries is a real URL. Without an item operation API
        // Platform derives a fallback IRI from the short name that routes nowhere, which is a
        // 404 shipped inside every response.
        new Get(
            uriTemplate: '/band_space_tech_rider_stage_plot_icons/{slug}',
            openapi: new Operation(tags: ['Band Space Tech Rider']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_tech_rider_stage_plot_icons_get_item',
            provider: TechRiderStagePlotIconProvider::class,
        ),
        new GetCollection(
            uriTemplate: '/band_space_tech_rider_stage_plot_icons',
            openapi: new Operation(tags: ['Band Space Tech Rider']),
            paginationEnabled: false,
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_tech_rider_stage_plot_icons_get_collection',
            provider: TechRiderStagePlotIconProvider::class,
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
)]
class TechRiderStagePlotIconResource
{
    /** The enum value, which is also the filename. One string to get right per icon. */
    #[ApiProperty(identifier: true)]
    public string $slug;

    public string $label;

    public string $category;

    public string $categoryLabel;

    /** Served rather than mapped in the browser so the four values live in one place. */
    public string $categoryColour;

    /** Unhashed and under public/, so this URL is stable across builds. */
    public string $imageUrl;
}
