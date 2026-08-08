/**
 * The sentence appended to a delete confirmation when the task or entry being deleted has files
 * attached to it.
 *
 * Deleting the source detaches its files but never deletes them: they stay in the library and keep
 * counting against the band's quota. Saying so at the point of no return is the only way the member
 * learns the space was not reclaimed.
 *
 * @param {number|null|undefined} count number of files attached to the source
 * @returns {string} an empty string when there is nothing attached, so it can be concatenated blindly
 */
export function attachedFilesNotice(count) {
  if (!count || count < 1) return ''
  if (count === 1) {
    return ' Le fichier attaché sera détaché mais restera dans Fichiers.'
  }

  return ` Les ${count} fichiers attachés seront détachés mais resteront dans Fichiers.`
}
