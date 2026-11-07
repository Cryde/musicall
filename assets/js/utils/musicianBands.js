export const MAX_BANDS = 5

/** An empty row of the edit form. */
export function emptyBand() {
  return { name: '', styleId: null, role: '', current: true, url: '' }
}

/** The stored bands as the edit form holds them, one editable row each. */
export function bandsForm(profile) {
  return (profile?.bands ?? []).map((band) => ({
    name: band.name,
    styleId: band.style_id ?? null,
    role: band.role ?? '',
    current: band.current,
    url: band.url ?? ''
  }))
}

/**
 * What the form sends, trimmed, an empty field sent as null. Only a row left fully empty is dropped: a row with a
 * role or a link but no name goes through, so the member reads why it is refused instead of losing it.
 */
export function bandsPayload(form) {
  return form
    .map((band) => ({
      name: band.name?.trim() ?? '',
      style_id: band.styleId || null,
      role: band.role?.trim() || null,
      current: band.current,
      url: band.url?.trim() || null
    }))
    .filter((band) => band.name || band.style_id || band.role || band.url)
    .slice(0, MAX_BANDS)
}

/** The band row a violation is about, counted from 1 as the form shows it, or null for any other field. */
export function bandRowOf(propertyPath) {
  const match = /^bands\[(\d+)\]/.exec(propertyPath ?? '')
  return match ? Number(match[1]) + 1 : null
}

/** The band named first in « Le musicien derrière le prof », and how many other current ones there are. */
export function currentBandsSummary(bands) {
  const current = (bands ?? []).filter((band) => band.current)
  if (!current.length) return null
  return { first: current[0], othersCount: current.length - 1 }
}

/** The bands as the profile lists them (#1317): the current ones first, each group in the order the member set. */
export function bandsCurrentFirst(bands) {
  const list = Array.isArray(bands) ? bands : []
  return [...list.filter((band) => band.current), ...list.filter((band) => !band.current)]
}
