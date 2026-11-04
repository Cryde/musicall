/**
 * Text as a search compares it: without accents, lower case, trimmed, so « lea » finds « Léa ».
 *
 * @param {string | null | undefined} text
 * @returns {string}
 */
export function foldForSearch(text) {
  return (text ?? '').normalize('NFD').replace(/\p{M}/gu, '').toLowerCase().trim()
}
