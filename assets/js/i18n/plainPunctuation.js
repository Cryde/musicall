// Typography that reads as machine written in English text, and the French non-breaking spaces a
// translation would carry over. Plain punctuation instead: a hyphen, a colon or a comma, three dots,
// straight quotes and apostrophes, ordinary spaces.
export const FORBIDDEN_CHARACTERS = {
  '\u2014': 'an em dash',
  '\u2013': 'an en dash',
  '\u2026': 'an ellipsis character',
  '\u2018': 'a curly quote',
  '\u2019': 'a curly apostrophe',
  '\u201C': 'a curly quote',
  '\u201D': 'a curly quote',
  '\u00AB': 'a guillemet',
  '\u00BB': 'a guillemet',
  '\u2039': 'a guillemet',
  '\u203A': 'a guillemet',
  '\u00B7': 'a middle dot',
  '\u00A0': 'a non-breaking space',
  '\u202F': 'a narrow no-break space'
}
// The rest of what the PHP guard on the server catalogues rules out (TranslationCatalogueTest).
export const FORBIDDEN_PATTERNS = [
  [/[\u2190-\u21FF]/, 'an arrow'],
  [/\s[:;!?]/, 'a space before a colon, a semicolon, an exclamation or a question mark']
]

/** The forbidden characters and patterns `message` contains, each named, for a test to report. */
export function punctuationViolations(message) {
  return Object.entries(FORBIDDEN_CHARACTERS)
    .filter(([character]) => message.includes(character))
    .map(([character, name]) => `${name} (${character})`)
    .concat(FORBIDDEN_PATTERNS.filter(([pattern]) => pattern.test(message)).map(([, name]) => name))
}
