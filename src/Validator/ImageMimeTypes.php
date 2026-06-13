<?php

declare(strict_types=1);

namespace App\Validator;

/**
 * Single source of truth for the raster image mime types accepted by every
 * `Assert\Image` upload constraint in the application.
 *
 * `image/svg+xml` is deliberately excluded: SVG is an XML document that runs
 * embedded JavaScript when rendered inline, and Symfony's ImageValidator skips
 * every dimension check for SVG, so without an explicit allowlist a crafted SVG
 * passes `Assert\Image` and becomes a stored-XSS vector. See SECURITY-FIX.md
 * finding 2. The mime is verified server-side via finfo, so it cannot be
 * spoofed by renaming the file.
 */
final class ImageMimeTypes
{
    /** @var list<string> */
    public const array ALLOWED = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    public const string INVALID_MESSAGE = 'Le format de l\'image n\'est pas autorisé. Formats acceptés : JPEG, PNG, GIF, WebP.';
}
