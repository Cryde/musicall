/**
 * What the finance delete confirmations say, kept here because both used to describe something other
 * than what the API does.
 *
 * The category one claimed "Les entrées et récurrences associées seront également supprimées" without
 * ever saying how many, on a dialog whose only other content is the word Supprimer. The recurrence one
 * never mentioned entries at all, while deleting one drops every forecast it had planned.
 */

/**
 * A category holding sub-categories is refused outright, so that is named first and instead of the
 * rest: a dialog that promises a deletion the server is about to refuse, for a reason it never
 * mentioned, is worse than the vague copy this replaced. Such a category can hold no entries at all
 * and still not be deletable, so the entry count alone cannot be trusted to describe the outcome.
 *
 * @param {number|null|undefined} entryCount entries filed directly under the category
 * @param {boolean} hasChildren whether it holds sub-categories
 * @returns {string}
 */
export function categoryDeleteMessage(entryCount, hasChildren = false) {
  if (hasChildren) {
    return 'Cette catégorie contient des sous-catégories et ne peut pas être supprimée. Supprimez ou déplacez ses sous-catégories d’abord.'
  }

  if (!entryCount || entryCount < 1) {
    return 'Es-tu sûr de vouloir supprimer cette catégorie ? Elle ne contient aucune entrée.'
  }

  if (entryCount === 1) {
    return 'Es-tu sûr de vouloir supprimer cette catégorie ? L’entrée qu’elle contient et ses récurrences seront également supprimées.'
  }

  return `Es-tu sûr de vouloir supprimer cette catégorie ? Les ${entryCount} entrées qu’elle contient et ses récurrences seront également supprimées.`
}

/**
 * Deleting a recurrence drops the entries it planned, but only those still Prévu: an occurrence that
 * was engaged or paid has stopped belonging to the series and stays in the accounts.
 */
export const RECURRENCE_DELETE_MESSAGE =
  'Es-tu sûr de vouloir supprimer cette récurrence ? Les entrées prévues qu’elle a générées seront également supprimées, les entrées engagées ou payées seront conservées.'
