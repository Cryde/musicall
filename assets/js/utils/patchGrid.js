/**
 * The rules of the patch list table (#1099), kept out of the component so they can be tested:
 * which channels a row takes, what a new row is numbered, and how a selection grows.
 */

/** A stereo row takes its channel and the next, as a pair on a console. */
export function occupiedChannels(row) {
  if (row.channel === null || row.channel === undefined) return []

  return row.stereo ? [row.channel, row.channel + 1] : [row.channel]
}

/** Every channel taken twice, stereo pairs included, so both rows of a clash can be marked. */
export function duplicateChannels(rows) {
  const seen = new Set()
  const duplicates = new Set()
  for (const channel of rows.flatMap(occupiedChannels)) {
    if (seen.has(channel)) duplicates.add(channel)
    seen.add(channel)
  }

  return duplicates
}

export function rowClashes(row, duplicates) {
  return occupiedChannels(row).some((channel) => duplicates.has(channel))
}

/** The last channel a row can take, as TechRiderPatchRows::MAX_CHANNEL. */
export const MAX_CHANNEL = 999

/**
 * One past the highest channel taken, so adding rows in order never lands on a duplicate. Null
 * past the last channel: the new row then asks for a number instead of being born invalid.
 */
export function nextChannel(rows) {
  const taken = rows.flatMap(occupiedChannels)
  const next = taken.length === 0 ? 1 : Math.max(...taken) + 1

  return next > MAX_CHANNEL ? null : next
}

/** The channels the rows get when renumbered in the order shown, a stereo row taking two. */
export function renumberedChannels(rows, start = 1) {
  let channel = start

  return rows.map((row) => {
    const assigned = channel
    channel += row.stereo ? 2 : 1
    return assigned
  })
}

/** How the channel reads in the table and the PDF: « 8-9 » for a stereo pair. */
export function channelLabel(row) {
  if (row.channel === null || row.channel === undefined) return ''

  return row.stereo ? `${row.channel}-${row.channel + 1}` : String(row.channel)
}

/** What a channel cell holds once typed into: digits only, empty is « no channel yet ». */
export function parseChannel(text) {
  const digits = String(text).replace(/\D/g, '')

  return digits === '' ? null : Number(digits)
}

/**
 * The selection after a click on a row's checkbox. A plain or Ctrl/Cmd click toggles that row;
 * Maj + clic selects every row between the last one clicked and this one, as a file manager does.
 *
 * @param {string[]} orderedKeys the rows in the order shown
 * @param {Set<string>} selected
 * @param {string|null} anchorKey the last row clicked without Maj
 * @param {string} clickedKey
 * @param {boolean} isRange whether Maj was held
 * @returns {{ selected: Set<string>, anchorKey: string }}
 */
export function selectionAfterClick(orderedKeys, selected, anchorKey, clickedKey, isRange) {
  const anchorIndex = anchorKey === null ? -1 : orderedKeys.indexOf(anchorKey)
  if (isRange && anchorIndex !== -1) {
    const clickedIndex = orderedKeys.indexOf(clickedKey)
    const [from, to] = [anchorIndex, clickedIndex].sort((a, b) => a - b)

    return {
      selected: new Set([...selected, ...orderedKeys.slice(from, to + 1)]),
      anchorKey
    }
  }

  const next = new Set(selected)
  if (next.has(clickedKey)) {
    next.delete(clickedKey)
  } else {
    next.add(clickedKey)
  }

  return { selected: next, anchorKey: clickedKey }
}

/**
 * What the Micro / DI cell offers for what has been typed so far: the catalogue, then the band's
 * own models with how often each is used, both narrowed to what contains the text.
 *
 * @param {string} query
 * @param {{ used: {name: string, usage_count: number}[], catalogue: string[] }} suggestions
 */
export function microphoneSuggestionGroups(query, suggestions) {
  const needle = query.trim().toLowerCase()
  const matches = (name) => needle === '' || name.toLowerCase().includes(needle)

  const groups = [
    {
      label: 'Suggestions',
      items: suggestions.catalogue.filter(matches).map((name) => ({ name, usageCount: null }))
    },
    {
      label: 'Déjà utilisés dans vos riders',
      items: suggestions.used
        .filter(({ name }) => matches(name))
        .map(({ name, usage_count }) => ({ name, usageCount: usage_count }))
    }
  ]

  return groups.filter((group) => group.items.length > 0)
}
