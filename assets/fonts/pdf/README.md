# PDF Embedded Fonts

These TTF files are embedded into every Set List PDF exported by `SetlistPdfRenderer`.
All three families are open-licensed; they are bundled here so PDFs render identically
regardless of the server's installed fonts.

| Family | Files | Upstream | License |
|---|---|---|---|
| Inter | `Inter-Regular.ttf`, `Inter-Bold.ttf` | https://github.com/rsms/inter (v3.19 static) | SIL Open Font License 1.1 |
| Atkinson Hyperlegible | `AtkinsonHyperlegible-Regular.ttf`, `AtkinsonHyperlegible-Bold.ttf` | https://github.com/google/fonts/tree/main/ofl/atkinsonhyperlegible | SIL Open Font License 1.1 |
| Source Serif | `SourceSerif-Regular.ttf`, `SourceSerif-Bold.ttf` | https://github.com/adobe-fonts/source-serif (v4.005) | SIL Open Font License 1.1 |

There is no font cache. `SetlistPdfRenderer` uploads the two TTFs of the selected family
alongside the HTML, and the template declares them with `@font-face`, so Gotenberg's Chromium
reads them straight from its own working directory. Only the chosen family travels, not all
three. The previous engine compiled a metrics cache named from the absolute source path, which
regenerated into a read-only release directory on every deploy and returned a 500 in production;
nothing here does that any more.

Adding a font:
1. Drop the Regular + Bold TTFs into this directory.
2. Add a case to `App\Enum\BandSpace\SetlistPdfFont` and implement `cssFamily()`,
   `regularFile()`, `boldFile()` for it. Those three methods are the single source of truth:
   the family name reaches the `@font-face` rule and the filenames become the uploaded assets.
3. Update the frontend dropdown in `PdfExportPopover.vue`.
