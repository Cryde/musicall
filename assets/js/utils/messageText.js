const NAMED_ENTITIES = { amp: '&', lt: '<', gt: '>', quot: '"', apos: "'", nbsp: '\u00a0' }

function decodeEntity(entity) {
  if (entity[0] === '#') {
    const isHex = entity[1] === 'x' || entity[1] === 'X'
    const codePoint = Number.parseInt(entity.slice(isHex ? 2 : 1), isHex ? 16 : 10)
    return Number.isNaN(codePoint) ? null : String.fromCodePoint(codePoint)
  }
  return NAMED_ENTITIES[entity.toLowerCase()] ?? null
}

/**
 * A message's text as it was typed, from the HTML the API sends: its line breaks back as new lines,
 * its tags dropped and its entities decoded. The API escapes the text and only adds `<br />` after
 * each line, so this is all a copy needs.
 *
 * @param {string | null | undefined} html
 * @returns {string}
 */
export function htmlToText(html) {
  return (html ?? '')
    .replace(/<br\s*\/?>\n?/gi, '\n')
    .replace(/<[^>]*>/g, '')
    .replace(/&(#x[\da-f]+|#\d+|[a-z]+);/gi, (match, entity) => decodeEntity(entity) ?? match)
}
