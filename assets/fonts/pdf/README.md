# PDF Embedded Fonts

These TTF files are embedded into every Set List PDF exported by `SetlistPdfRenderer`.
All three families are open-licensed; they are bundled here so PDFs render identically
regardless of the server's installed fonts.

| Family | Files | Upstream | License |
|---|---|---|---|
| Inter | `Inter-Regular.ttf`, `Inter-Bold.ttf` | https://github.com/rsms/inter (v3.19 static) | SIL Open Font License 1.1 |
| Atkinson Hyperlegible | `AtkinsonHyperlegible-Regular.ttf`, `AtkinsonHyperlegible-Bold.ttf` | https://github.com/google/fonts/tree/main/ofl/atkinsonhyperlegible | SIL Open Font License 1.1 |
| Source Serif | `SourceSerif-Regular.ttf`, `SourceSerif-Bold.ttf` | https://github.com/adobe-fonts/source-serif (v4.005) | SIL Open Font License 1.1 |

dompdf writes its font metrics cache to `var/cache/dompdf/` (gitignored). To force a
rebuild of the cache, delete that directory; it will be regenerated on the next render.

Adding a font:
1. Drop the Regular + Bold TTFs into this directory.
2. Add a case to `App\Enum\BandSpace\SetlistPdfFont` and implement `dompdfFamily()`,
   `regularFile()`, `boldFile()` for it (these methods are the single source of
   truth — `SetlistPdfRenderer` iterates `SetlistPdfFont::cases()` at render time).
3. Update the frontend dropdown in `PdfExportPopover.vue`.
