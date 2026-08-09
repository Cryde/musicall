/**
 * The sentence to show the user for a failed request.
 *
 * A 4xx from this API carries a message written for the band ("Cette catégorie contient une entrée
 * payée..."), and dropping it for a generic "Impossible de supprimer" throws away the only part that
 * says what to do next. A 5xx or a network failure carries a technical string in English, which the
 * caller's own wording replaces.
 *
 * @param {{status?: number, message?: string}|null|undefined} error a normalized error from handleApiError
 * @param {string} fallback wording to use when the error has nothing worth showing
 * @returns {string}
 */
export function apiErrorDetail(error, fallback) {
  const status = error?.status ?? 0

  return status >= 400 && status < 500 && error?.message ? error.message : fallback
}
