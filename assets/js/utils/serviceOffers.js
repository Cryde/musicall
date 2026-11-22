/** A service's « Formules » (#1410, #1411) as its form edits them, kept out of the components so they are tested. */

let rowKey = 0

/** A stored offer, or a new one (`{ unit }`), as a row of the form: its price in euros, and a key of its own. */
export function offerRow(offer) {
  rowKey += 1
  return {
    key: rowKey,
    unit: offer.unit,
    title: offer.title ?? '',
    description: offer.description ?? '',
    price: offer.price == null ? null : offer.price / 100
  }
}

/**
 * The offers as the API stores them: prices typed in euros, sent in cents; a row without a title is dropped. A missing
 * price stays missing, so the API asks for one rather than storing a free offer.
 */
export function offersPayload(rows) {
  return rows
    .filter((row) => row.title?.trim())
    .map((row) => ({
      unit: row.unit,
      title: row.title.trim(),
      description: row.description?.trim() || null,
      price: row.price == null ? null : Math.round(row.price * 100)
    }))
}
