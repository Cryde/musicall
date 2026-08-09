/**
 * The resources a band space file can be attached to, in the wording the API refuses with.
 *
 * The backend keeps the same five in `BandSpaceFileSourceTypes::ALL` and words them in
 * `BandSpaceFileAttachmentLabels`; both delete paths refuse to trash a file attached to any of them.
 * This list is what tells the user which ones that covers, so a sixth source type is two edits, one
 * on each side, and never a screen quietly naming three of five.
 */
export const FILE_SOURCE_NOUNS = Object.freeze([
  'une tâche',
  'une note',
  'une entrée financière',
  'une chanson',
  'une setlist'
])

/** « une tâche, une note, ... ou une setlist », for prose listing every source at once. */
export const FILE_SOURCE_LIST_LABEL = `${FILE_SOURCE_NOUNS.slice(0, -1).join(', ')} ou ${FILE_SOURCE_NOUNS.at(-1)}`
