/**
 * How many filters the phone's « Filtres » sheet holds are on, for the count on its chip. « Mes
 * tâches » and the search have their own place on the screen, so they are left out, and a due date
 * range counts once whether it has one end or two.
 *
 * @param {object} filters the tasks store's filters
 * @returns {number}
 */
export function sheetFilterCount(filters) {
  return [
    filters.categoryId,
    filters.assigneeId,
    filters.unassigned,
    filters.priority,
    filters.overdue,
    filters.dueDateFrom || filters.dueDateTo,
    filters.showArchived
  ].filter(Boolean).length
}
