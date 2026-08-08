# Stage Plot Icons

One PNG per case of `App\Enum\BandSpace\TechRiderStagePlotIcon`. The enum value is the filename,
so there is a single string to get right per icon, and `imagePath()` is the only place that builds
the URL.

They live under `public/` rather than `assets/` on purpose. Vite fingerprints anything in
`assets/` at build time, and a hashed filename would mean the picker, the stored plot document and
any future server side renderer could each end up naming a different file. An unhashed path keeps
one name, and a renderer reading from disk can predict it.

PNG with transparency rather than SVG. The original reason was that the export engine was undecided
(#741); it is now Chromium, which renders SVG properly, so SVG has become viable.

**Real artwork is drawn as SVG and lives in `assets/icons/stage_plot/` instead** (#836). The two
formats coexist on purpose: an icon with a drawn symbol uses it everywhere, and one still on its
placeholder keeps the PNG below. `TechRiderStagePlotIcon::symbolPath()` is what decides, and it
returns null for an icon with no symbol yet.

Symbols are inlined rather than served, so they take the element's colour through `currentColor`.
An SVG behind an `<img>` is an isolated document that the page's colour cannot reach, which would
render every symbol black. That is also why they do not need the stable unhashed path the PNGs do:
no URL is involved.

## Current artwork: placeholders

**Every file in this directory is a generated placeholder**, not final art: a rounded square in the
category colour with the label's initials. They exist so the editor (#774) is buildable and so the
catalogue endpoint returns something that renders.

Seven of the twenty-one now have real artwork in `assets/icons/stage_plot/` and no longer render
from here, though their placeholder file stays so the catalogue's `image_url` keeps resolving.

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
| _all PNG placeholders_ | generated placeholder, this repository | n/a |
| `assets/icons/stage_plot/*.svg` | drawn for this project | this repository |

## Adding an icon

1. Add a case to `TechRiderStagePlotIcon` with its `category()` and French `label()`.
2. Drop `{slug}.png` in this directory.

## Drawing a real symbol for one

1. Draw it on a `0 0 64 64` viewBox, top down, `fill="none"`, `stroke="currentColor"`.
2. Set no colour and no `stroke-width` attribute. Take the stroke from
   `style="stroke-width:var(--sp-stroke, 2)"`, and express any thinner or thicker stroke, and any
   filled detail, as a `calc()` multiple of it so the whole set scales together.
3. Save it as `assets/icons/stage_plot/{slug}.svg`.
4. Add the case to the `symbolPath()` match.

`TechRiderStagePlotIconArtworkTest` fails if either half of either format is missing, and checks
that a symbol hardcodes no colour and follows the stroke constant, so these cannot drift apart
unnoticed.

## Calibration

`SYMBOL_STROKE_WIDTH` and `SYMBOL_SIZE_PERCENT` in `assets/js/constants/stagePlot.js`, mirrored in
`TechRiderPdfRenderer`, are the two knobs. They exist to be tuned after printing a rider on A4: an
element is 10.9mm wide at scale 1, so stroke 1.4 is 0.24mm of ink.
