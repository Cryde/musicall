/**
 * Whether the detach confirmation may offer « Déplacer aussi vers la corbeille » (#829).
 *
 * Mirrors BandSpaceDetachedFileArchiver, which refuses a detach's `?archive=true` on the trash
 * button's two grounds: the member neither uploaded the file nor administers the space, or another
 * source still holds it. Offering the box anyway would only move the refusal after the click.
 *
 * Run with `npm test`.
 */

import { deleteBlockingAttachments } from '../constants/fileSources.js'
import { isFileCreatorOrAdmin } from './bandSpaceFilePermissions.js'

/**
 * @param {{created_by?: {id?: string}, attachments?: {source_type?: string, source_id?: string}[]}} file
 * @param {{type: string, id: string}} source what the file is being detached from
 * @param {string|null} currentUserId
 * @param {boolean} isAdmin
 * @returns {{offered: boolean, keptBy: string[]}} `keptBy` holds the types of the other sources when
 *          they are what rules the trash out, so the dialog can say why; empty otherwise
 */
export function detachTrashOption(file, source, currentUserId, isAdmin) {
  if (!isFileCreatorOrAdmin(file, currentUserId, isAdmin)) {
    return { offered: false, keptBy: [] }
  }

  const keptBy = deleteBlockingAttachments(file.attachments)
    .filter(
      (attachment) => attachment.source_type !== source.type || attachment.source_id !== source.id
    )
    .map((attachment) => attachment.source_type)

  return { offered: keptBy.length === 0, keptBy }
}
