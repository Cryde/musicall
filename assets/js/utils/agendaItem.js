/**
 * The rule that decides whether an agenda item has a time worth showing.
 *
 * It lives outside the components because the agenda view and the dashboard widget both render
 * agenda items and have to answer this the same way. They did not: the widget printed a clock on
 * every item and so showed 02:00 on all day events and on finance due dates. It is a pure
 * predicate, so it is unit tested without a browser (npm test).
 */

/** The only source whose datetime carries a time the user actually typed. */
const TIMED_SOURCE = 'manual'

/**
 * Whether the item covers a day rather than happening at a moment.
 *
 * All day entries are stored at UTC midnight, and task and finance items are built from date only
 * columns the aggregator pads to midnight too. Formatting any of them as a clock therefore prints
 * the reader's own UTC offset, so 02:00 in France in summer and 01:00 in winter: a time nobody
 * ever entered. An unknown source reads as all day, so a future aggregated source cannot start
 * printing that padding before anyone notices.
 *
 * Takes the API shape of an agenda item, so snake_case properties.
 */
export function isAllDayItem(item) {
  return item?.source !== TIMED_SOURCE || item?.is_all_day === true
}

/**
 * Where an agenda item leads outside the agenda (#1159): its entry on its occurrence, the task, or
 * the finance entry. An absence has no screen of its own to open it in, so it leads nowhere.
 *
 * @returns {object | null} a route location
 */
export function agendaItemLink(item, bandSpaceId) {
  if (!item?.source_id) return null
  const params = { id: bandSpaceId }

  if (item.source === 'manual') {
    const occurrence = item.metadata?.occurrence_date
    return {
      name: 'app_band_agenda',
      params,
      query: occurrence ? { entry: item.source_id, occurrence } : { entry: item.source_id }
    }
  }
  if (item.source === 'task') {
    return { name: 'app_band_tasks', params, query: { task: item.source_id } }
  }
  if (item.source === 'finance') {
    return { name: 'app_band_finance', params, query: { entry: item.source_id } }
  }

  return null
}
