# Stage Plot Icons

One PNG per case of `App\Enum\BandSpace\TechRiderStagePlotIcon`. The enum value is the filename,
so there is a single string to get right per icon, and `imagePath()` is the only place that builds
the URL.

They live under `public/` rather than `assets/` on purpose. Vite fingerprints anything in
`assets/` at build time, and a hashed filename would mean the picker, the stored plot document and
any future server side renderer could each end up naming a different file. An unhashed path keeps
one name, and a renderer reading from disk can predict it.

PNG with transparency rather than SVG. The original reason was that the export engine was undecided
(#741); it is now Chromium, which renders SVG properly, so SVG has become viable. PNG stays because
these are small raster badges either way and swapping formats would mean redrawing all 21.

## Current artwork: placeholders

**Every file here is a generated placeholder**, not final art: a rounded square in the category
colour with the label's initials. They exist so the editor (#774) is buildable and so the
catalogue endpoint returns something that renders.

| Category | Placeholder colour |
|---|---|
| Son | blue `#2563eb` |
| Instruments | green `#059669` |
| Lumière | amber `#d97706` |
| Divers | slate `#64748b` |

Replacing them is a drop-in: same filenames, same 256x256 canvas, transparent background. Nothing
in the code changes.

**When the real set lands, record the source and licence of every file in the table below**, the
way `assets/fonts/pdf/README.md` does for the bundled fonts. A stage plot is printed in a document
that leaves the band, so the licence of what is printed has to be known.

| File | Source | Licence |
|---|---|---|
| _all_ | generated placeholder, this repository | n/a |

## Adding an icon

1. Add a case to `TechRiderStagePlotIcon` with its `category()` and French `label()`.
2. Drop `{slug}.png` in this directory.

`TechRiderStagePlotIconArtworkTest` fails if a case has no file, so the two halves cannot drift
apart unnoticed.
