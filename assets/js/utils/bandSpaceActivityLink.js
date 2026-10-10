/**
 * Where an activity line leads (#1159): the screen holding the item its `resource_id` names, or null.
 *
 * Chosen per type, not per module, because the id is not always the item a screen opens: a finance
 * category or recurrence, a folder, a rider section, a member or an invitation has no screen of its
 * own, and a deleted or trashed item has nothing left to open. Those lines stay plain text.
 */

/** Each module's screen, the query key it opens an item by, and which types name such an item. */
const LINKS = {
  task: [{ route: 'app_band_tasks', key: 'task', types: 'all' }],
  notes: [{ route: 'app_band_notes', key: 'note', types: { except: ['note_deleted'] } }],
  agenda: [
    {
      route: 'app_band_agenda',
      key: 'entry',
      types: { except: ['entry_deleted', 'feed_generated', 'feed_revoked'] }
    }
  ],
  finance: [
    {
      route: 'app_band_finance',
      key: 'entry',
      types: {
        only: [
          'entry_created',
          'entry_status_changed',
          'entry_label_changed',
          'entry_amount_changed',
          'entry_category_changed',
          'entry_date_changed',
          'split_added',
          'split_removed'
        ]
      }
    }
  ],
  file: [
    {
      route: 'app_band_files',
      key: 'file',
      types: {
        only: [
          'uploaded',
          'restored',
          'renamed',
          'moved',
          'tagged',
          'untagged',
          'version_added',
          'rolled_back',
          'shared',
          'share_revoked',
          'public_accessed',
          'attached',
          'detached',
          'source_deleted'
        ]
      }
    }
  ],
  setlist: [
    {
      route: 'app_band_setlist',
      key: 'setlist',
      types: {
        only: [
          'setlist_created',
          'setlist_renamed',
          'setlist_duplicated',
          'setlist_unarchived',
          'setlist_item_added',
          'setlist_items_added',
          'setlist_items_copied',
          'setlist_item_removed',
          'setlist_item_reordered',
          'setlist_item_updated',
          'setlist_file_attached',
          'setlist_file_detached'
        ]
      }
    },
    {
      route: 'app_band_setlist',
      key: 'song',
      types: {
        only: [
          'song_added',
          'song_updated',
          'song_unarchived',
          'song_file_attached',
          'song_file_detached'
        ]
      }
    }
  ],
  rider: [
    {
      route: 'app_band_rider',
      key: 'rider',
      types: {
        only: [
          'rider_created',
          'rider_renamed',
          'rider_duplicated',
          'rider_unarchived',
          'rider_item_reordered'
        ]
      }
    }
  ]
}

function namesItem(types, type) {
  if (types === 'all') return true
  if (types.only) return types.only.includes(type)
  return !types.except.includes(type)
}

/**
 * @param {{module: string, type: string, resource_id: string|null}} activity
 * @param {string} bandSpaceId
 * @returns {object | null} a route location
 */
export function activityLink(activity, bandSpaceId) {
  if (!activity?.resource_id) return null

  const link = (LINKS[activity.module] ?? []).find((candidate) =>
    namesItem(candidate.types, activity.type)
  )
  if (!link) return null

  return {
    name: link.route,
    params: { id: bandSpaceId },
    query: { [link.key]: activity.resource_id }
  }
}
