/**
 * Which bulk actions the API will accept, decided on the rows the list already holds.
 *
 * Trashing a file is the uploader's or an administrator's, and a file some task or note still points
 * at cannot be trashed at all. A bulk write is one transaction, so a single row in the way takes the
 * whole selection with it: a member who ticked twelve rows then reads a refusal naming none of them.
 * Working it out here lets the bar grey the button out and say which files it is waiting on, instead
 * of letting somebody confirm a destructive dialog to find out. The server still has the last word.
 *
 * The sentences are byte for byte the ones BandSpaceFileBulkDeleteProcedure and
 * BandSpaceFileBulkRestoreProcedure throw, so the greyed out tooltip and the refusal read the same.
 *
 * Run with `npm test`.
 */

import { describeFileSources } from '../constants/fileSources.js'
import { isFileCreatorOrAdmin } from './bandSpaceFilePermissions.js'

/**
 * The names of the selected files the member neither uploaded nor administrates.
 *
 * @param {{original_name: string, created_by?: {id?: string}}[]} files
 * @param {string|null} currentUserId
 * @param {boolean} isAdmin
 * @returns {string[]}
 */
export function filesNotOwnedBy(files, currentUserId, isAdmin) {
  return files
    .filter((file) => !isFileCreatorOrAdmin(file, currentUserId, isAdmin))
    .map((file) => file.original_name)
}

/**
 * The names of the selected files something else still points at.
 *
 * @param {{original_name: string, attachments?: object[]}[]} files
 * @returns {string[]}
 */
export function attachedFiles(files) {
  return files.filter((file) => (file.attachments?.length ?? 0) > 0)
}

/**
 * Mirrors BandSpaceFileBulkDeleteProcedure::assertEveryFileIsOwnedBy.
 *
 * @returns {string|null} null when nothing blocks
 */
export function deleteOwnershipReason(files, currentUserId, isAdmin) {
  const blocking = filesNotOwnedBy(files, currentUserId, isAdmin)

  return blocking.length === 0
    ? null
    : `Seul le créateur ou un administrateur peut supprimer ces fichiers : ${blocking.join(', ')}`
}

/**
 * Mirrors BandSpaceFileBulkRestoreProcedure::assertEveryFileIsOwnedBy.
 *
 * @returns {string|null} null when nothing blocks
 */
export function restoreOwnershipReason(files, currentUserId, isAdmin) {
  const blocking = filesNotOwnedBy(files, currentUserId, isAdmin)

  return blocking.length === 0
    ? null
    : `Seul le créateur ou un administrateur peut restaurer ces fichiers : ${blocking.join(', ')}`
}

/**
 * Mirrors BandSpaceFileBulkDeleteProcedure::assertNoAttachedFile, singular and plural both.
 *
 * The source types are sorted before being named, exactly as the server sorts them, so the two
 * sentences enumerate the sources in the same order.
 *
 * @param {{original_name: string, attachments?: {source_type?: string}[]}[]} files
 * @returns {string|null} null when nothing blocks
 */
export function attachmentReason(files) {
  const blocking = attachedFiles(files)
  if (blocking.length === 0) {
    return null
  }

  const sources = describeFileSources(
    blocking.flatMap((file) => file.attachments.map((attachment) => attachment.source_type)).sort()
  )

  return blocking.length === 1
    ? `1 fichier sélectionné est attaché à ${sources}. Détachez-le d'abord depuis la ressource concernée.`
    : `${blocking.length} fichiers sélectionnés sont attachés à ${sources}. Détachez-les d'abord depuis les ressources concernées.`
}

/**
 * The one sentence to show under a disabled Supprimer button.
 *
 * Ownership comes first because it is the refusal the server reaches first, so the member is never
 * told to detach files only to be refused again for a reason nobody mentioned.
 *
 * @returns {string|null}
 */
export function deleteBlockedReason(files, currentUserId, isAdmin) {
  return deleteOwnershipReason(files, currentUserId, isAdmin) ?? attachmentReason(files)
}
