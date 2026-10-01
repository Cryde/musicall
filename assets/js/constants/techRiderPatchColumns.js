/**
 * The patch list's columns, in order, as both the editor and the PDF name them (#1089). Mirrors the
 * PHP enum TechRiderPatchColumn, pinned by TechRiderPatchColumnsTest: change a label on one side
 * only and that test fails.
 *
 * The placeholders show only on a row nobody has typed into yet, so an example is never mistaken
 * for a value the PDF would then print as a dash.
 */
export const PATCH_LIST_COLUMNS = Object.freeze([
  { field: 'channel', label: 'Canal' },
  { field: 'name', label: 'Nom', placeholder: 'ex. KICK IN' },
  { field: 'microphone', label: 'Micro', placeholder: 'ex. Beta 91' },
  { field: 'routing', label: 'Routage', placeholder: 'ex. Split A1' },
  { field: 'colour', label: 'Couleur' }
])

/** Mirrors TechRiderPatchDirection::label(). */
export const PATCH_LIST_DIRECTIONS = Object.freeze({
  inputs: 'Entrées',
  outputs: 'Retours'
})

export const PATCH_LIST_LABELS = Object.freeze(
  Object.fromEntries(PATCH_LIST_COLUMNS.map(({ field, label }) => [field, label]))
)
