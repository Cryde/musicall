/**
 * Which task actions the API will accept, decided on the board's own data.
 *
 * Deleting a task is the creator's or an administrator's, and only a finished task can be
 * archived. A bulk write is one transaction, so a single card in the way takes the whole selection
 * with it: a member who ticked eight cards then reads a refusal that names none of them. Working
 * it out here lets the board grey out what would be refused and say which cards are responsible,
 * instead of letting somebody confirm a destructive dialog to find out. The server still has the
 * last word; this only spares the round trip and the surprise.
 */

/** The only status the archive endpoint accepts. */
const DONE_STATUS = 'done'

/**
 * Mirrors TaskDeleteProcessor: the task's creator, or an administrator of the band space.
 *
 * @param {{created_by_id?: string}} task
 * @param {string|null} currentUserId
 * @param {boolean} isAdmin Whether the current member administrates the band space.
 * @returns {boolean}
 */
export function canDeleteTask(task, currentUserId, isAdmin) {
  if (isAdmin) {
    return true
  }

  return (
    currentUserId !== null && currentUserId !== undefined && task?.created_by_id === currentUserId
  )
}

/**
 * The titles of the selected tasks a bulk delete would be refused over.
 *
 * @param {{title: string, created_by_id?: string}[]} tasks
 * @param {string|null} currentUserId
 * @param {boolean} isAdmin
 * @returns {string[]}
 */
export function tasksBlockingDelete(tasks, currentUserId, isAdmin) {
  return tasks
    .filter((task) => !canDeleteTask(task, currentUserId, isAdmin))
    .map((task) => task.title)
}

/**
 * The titles of the selected tasks a bulk archive would be refused over.
 *
 * Mirrors TaskBulkPatchProcedure, down to leaving out a task that is already archived: archiving
 * it again is a no-op there, never a refusal.
 *
 * @param {{title: string, status: string, archive_datetime?: string|null}[]} tasks
 * @returns {string[]}
 */
export function tasksBlockingArchive(tasks) {
  return tasks
    .filter((task) => task.status !== DONE_STATUS && !task.archive_datetime)
    .map((task) => task.title)
}
