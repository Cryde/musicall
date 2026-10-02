import { channelLabel } from './patchGrid.js'

/**
 * What the « Prêt à envoyer » card counts (#1093). Two plain rules, no engine: a section visible in
 * the PDF must not be empty, and every input of a patch list needs a source and a microphone,
 * since those are what the venue's engineer patches from.
 */

function plural(count, singular, pluralForm) {
  return `${count} ${count > 1 ? pluralForm : singular}`
}

/** « canal 7 », « canaux 3, 8-9 »: the channels of the rows concerned, as printed. */
function channelsOf(rows) {
  const labels = rows.map(channelLabel).filter(Boolean)
  if (labels.length === 0) return ''

  return ` (${labels.length > 1 ? 'canaux' : 'canal'} ${labels.join(', ')})`
}

function patchListProblems(item) {
  const inputs = item.patch_list?.inputs ?? []
  const withoutName = inputs.filter((row) => !row.name?.trim())
  const withoutMicrophone = inputs.filter((row) => !row.microphone?.trim())

  return [
    withoutName.length > 0
      ? `${plural(withoutName.length, 'entrée sans nom', 'entrées sans nom')}${channelsOf(withoutName)}`
      : null,
    withoutMicrophone.length > 0
      ? `${plural(withoutMicrophone.length, 'entrée sans micro', 'entrées sans micro')}${channelsOf(withoutMicrophone)}`
      : null
  ].filter(Boolean)
}

/**
 * What is missing in a section that has content, worded for the card. Empty when nothing is. An
 * empty section is not asked here: riderReadiness lists those apart.
 */
export function sectionProblems(item) {
  return item.type === 'patch_list' ? patchListProblems(item) : []
}

/**
 * Counted over the sections visible in the PDF only: a hidden one is not sent. Empty sections are
 * listed apart, as one line on the card: a fresh rider has several, and a line each buried the rest.
 *
 * @param {object[]} items the rider's sections, as the API returns them
 * @returns {{
 *   total: number,
 *   ready: number,
 *   empty: { itemId: string, title: string }[],
 *   problems: { itemId: string, title: string, messages: string[] }[]
 * }}
 */
export function riderReadiness(items) {
  const visible = items.filter((item) => item.is_included)
  const empty = visible
    .filter((item) => item.is_empty)
    .map((item) => ({ itemId: item.id, title: item.title }))
  const problems = visible
    .filter((item) => !item.is_empty)
    .map((item) => ({ itemId: item.id, title: item.title, messages: sectionProblems(item) }))
    .filter((problem) => problem.messages.length > 0)

  return {
    total: visible.length,
    ready: visible.length - empty.length - problems.length,
    empty,
    problems
  }
}
