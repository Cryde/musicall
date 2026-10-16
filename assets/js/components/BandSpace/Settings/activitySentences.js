import { i18n } from '../../../i18n/index.js'
import { formatEurosFromCents } from '../../../utils/currency.js'
import { formatDateLong } from '../../../utils/date.js'

const TASK_STATUS_KEYS = {
  todo: 'band_space_activity.task_status.todo',
  in_progress: 'band_space_activity.task_status.in_progress',
  done: 'band_space_activity.task_status.done'
}

const FINANCE_STATUS_KEYS = {
  planned: 'band_space_activity.finance_status.planned',
  committed: 'band_space_activity.finance_status.committed',
  paid: 'band_space_activity.finance_status.paid'
}

// Translated when a sentence is built, never at import, so a language switch reaches the feed.
function t(key, ...args) {
  return i18n.global.t(key, ...args)
}

// A raw payload value as the template literals printed it, `undefined` included.
function raw(value) {
  return String(value)
}

function taskStatus(value) {
  return TASK_STATUS_KEYS[value] ? t(TASK_STATUS_KEYS[value]) : raw(value)
}

function financeStatus(value) {
  return FINANCE_STATUS_KEYS[value] ? t(FINANCE_STATUS_KEYS[value]) : raw(value)
}

function member(username) {
  return username ?? t('band_space_activity.a_member')
}

function untitled(title) {
  return title ?? t('band_space_activity.untitled')
}

// What the inviter typed: an address, or a username for an invitation made by username (#1119).
function invitee(payload) {
  if (payload?.email) return payload.email
  if (payload?.invited_username) return `@${payload.invited_username}`
  return t('band_space_activity.a_user')
}

// A `yyyy-MM-dd` from a payload, spelled out like every other date in the app (#1162).
function payloadDate(value) {
  return value ? formatDateLong(value) : '?'
}

function formatAmount(cents) {
  if (cents === null || cents === undefined) {
    return '—'
  }
  return formatEurosFromCents(cents)
}

const SENTENCES = {
  // Tasks
  'task.status_changed': (a) =>
    t('band_space_activity.task.status_changed', {
      from: taskStatus(a.payload?.from),
      to: taskStatus(a.payload?.to)
    }),
  'task.due_date_changed': () => t('band_space_activity.task.due_date_changed'),
  'task.category_changed': () => t('band_space_activity.task.category_changed'),
  'task.assignee_added': (a) =>
    t('band_space_activity.task.assignee_added', {
      member: member(a.payload?.assignee_username)
    }),
  'task.assignee_removed': (a) =>
    t('band_space_activity.task.assignee_removed', {
      member: member(a.payload?.assignee_username)
    }),
  'task.task_archived': () => t('band_space_activity.task.task_archived'),
  'task.task_unarchived': () => t('band_space_activity.task.task_unarchived'),
  'task.comment_added': () => t('band_space_activity.task.comment_added'),
  'task.comment_edited': () => t('band_space_activity.task.comment_edited'),
  'task.comment_deleted': () => t('band_space_activity.task.comment_deleted'),
  'task.mention': (a) =>
    t('band_space_activity.task.mention', { member: member(a.payload?.mentioned_username) }),

  // Agenda
  'agenda.entry_created': (a) =>
    t('band_space_activity.agenda.entry_created', { title: untitled(a.payload?.title) }),
  'agenda.entry_deleted': (a) =>
    t('band_space_activity.agenda.entry_deleted', { title: untitled(a.payload?.title) }),
  'agenda.title_changed': (a) =>
    t('band_space_activity.agenda.title_changed', {
      from: raw(a.payload?.from),
      to: raw(a.payload?.to)
    }),
  'agenda.description_changed': () => t('band_space_activity.agenda.description_changed'),
  'agenda.location_changed': (a) =>
    a.payload?.to
      ? t('band_space_activity.agenda.location_changed', { location: a.payload.to })
      : t('band_space_activity.agenda.location_cleared'),
  'agenda.event_datetime_changed': () => t('band_space_activity.agenda.event_datetime_changed'),
  'agenda.end_datetime_changed': () => t('band_space_activity.agenda.end_datetime_changed'),
  'agenda.is_all_day_changed': (a) =>
    a.payload?.to
      ? t('band_space_activity.agenda.all_day_on')
      : t('band_space_activity.agenda.all_day_off'),
  // The civil date is the day members saw; rows written before #1162 only carry the UTC key.
  'agenda.occurrence_cancelled': (a) =>
    t('band_space_activity.agenda.occurrence_cancelled', {
      date: payloadDate(a.payload?.occurrence_civil_date ?? a.payload?.occurrence_date),
      title: untitled(a.payload?.title)
    }),
  'agenda.feed_generated': () => t('band_space_activity.agenda.feed_generated'),
  'agenda.feed_revoked': () => t('band_space_activity.agenda.feed_revoked'),
  'agenda.series_truncated': (a) =>
    t('band_space_activity.agenda.series_truncated', {
      title: untitled(a.payload?.title),
      date: payloadDate(a.payload?.from_occurrence_date)
    }),

  // Notes
  'notes.note_created': (a) =>
    t('band_space_activity.notes.note_created', { title: untitled(a.payload?.title) }),
  'notes.note_deleted': (a) =>
    t('band_space_activity.notes.note_deleted', { title: untitled(a.payload?.title) }),
  'notes.note_renamed': (a) =>
    t('band_space_activity.notes.note_renamed', {
      from: raw(a.payload?.from),
      to: raw(a.payload?.to)
    }),
  'notes.note_emoji_changed': () => t('band_space_activity.notes.note_emoji_changed'),
  'notes.note_content_updated': () => t('band_space_activity.notes.note_content_updated'),
  'notes.note_file_attached': (a) =>
    t('band_space_activity.notes.note_file_attached', { name: a.payload?.original_name ?? '—' }),
  // No notes.note_file_detached — note detach is automatic cleanup when an
  // image is removed from the note body; the parallel note_content_updated
  // activity already covers it in the Notes feed.

  // Finance — entries
  'finance.entry_created': (a) =>
    t('band_space_activity.finance.entry_created', {
      label: a.payload?.label ?? '—',
      amount: formatAmount(a.payload?.amount)
    }),
  'finance.entry_deleted': (a) =>
    t('band_space_activity.finance.entry_deleted', { label: a.payload?.label ?? '—' }),
  'finance.entry_status_changed': (a) =>
    t('band_space_activity.finance.entry_status_changed', {
      from: financeStatus(a.payload?.from),
      to: financeStatus(a.payload?.to)
    }),
  'finance.entry_label_changed': (a) =>
    t('band_space_activity.finance.entry_label_changed', {
      from: raw(a.payload?.from),
      to: raw(a.payload?.to)
    }),
  'finance.entry_amount_changed': (a) =>
    t('band_space_activity.finance.entry_amount_changed', {
      from: formatAmount(a.payload?.from),
      to: formatAmount(a.payload?.to)
    }),
  'finance.entry_date_changed': () => t('band_space_activity.finance.entry_date_changed'),
  'finance.entry_category_changed': (a) =>
    t('band_space_activity.finance.entry_category_changed', {
      category: a.payload?.to_name ?? '—'
    }),

  // Finance — splits
  'finance.split_added': (a) =>
    t('band_space_activity.finance.split_added', {
      amount: formatAmount(a.payload?.amount),
      member: member(a.payload?.member_username)
    }),
  'finance.split_removed': (a) =>
    t('band_space_activity.finance.split_removed', {
      member: member(a.payload?.member_username)
    }),

  // Finance — categories
  'finance.category_created': (a) =>
    t('band_space_activity.finance.category_created', { name: a.payload?.name ?? '—' }),
  'finance.category_renamed': (a) =>
    t('band_space_activity.finance.category_renamed', {
      from: raw(a.payload?.from),
      to: raw(a.payload?.to)
    }),
  'finance.category_deleted': (a) =>
    t('band_space_activity.finance.category_deleted', { name: a.payload?.name ?? '—' }),
  'finance.categories_bootstrapped': (a) =>
    t('band_space_activity.finance.categories_bootstrapped', {
      count: a.payload?.count ?? '?'
    }),

  // Finance — recurrences
  'finance.recurrence_created': (a) =>
    t('band_space_activity.finance.recurrence_created', {
      label: a.payload?.label ?? '—',
      generated: a.payload?.generated_entries ?? 0
    }),
  'finance.recurrence_updated': (a) =>
    t('band_space_activity.finance.recurrence_updated', {
      fields: (a.payload?.changed_fields ?? []).join(', ')
    }),
  'finance.recurrence_started': () => t('band_space_activity.finance.recurrence_started'),
  'finance.recurrence_stopped': () => t('band_space_activity.finance.recurrence_stopped'),
  'finance.recurrence_end_date_changed': () =>
    t('band_space_activity.finance.recurrence_end_date_changed'),
  'finance.recurrence_deleted': (a) =>
    t('band_space_activity.finance.recurrence_deleted', { label: a.payload?.label ?? '—' }),

  // Setlist — songs (repertoire)
  'setlist.song_added': (a) =>
    t('band_space_activity.setlist.song_added', { title: a.payload?.title ?? '—' }),
  'setlist.song_updated': (a) =>
    t('band_space_activity.setlist.song_updated', { title: a.payload?.title ?? '—' }),
  'setlist.song_archived': (a) =>
    t('band_space_activity.setlist.song_archived', { title: a.payload?.title ?? '—' }),
  'setlist.songs_archived': (a) => {
    if (a.payload?.count === 1) return t('band_space_activity.setlist.songs_archived_one')
    if (a.payload?.count == null) return t('band_space_activity.setlist.songs_archived_some')
    return t('band_space_activity.setlist.songs_archived', { count: a.payload.count })
  },
  'setlist.song_unarchived': (a) =>
    t('band_space_activity.setlist.song_unarchived', { title: a.payload?.title ?? '—' }),
  'setlist.song_file_attached': (a) =>
    t('band_space_activity.setlist.song_file_attached', {
      name: a.payload?.original_name ?? '—'
    }),
  'setlist.song_file_detached': (a) =>
    t('band_space_activity.setlist.song_file_detached', {
      name: a.payload?.original_name ?? '—'
    }),

  // Setlist — setlists
  'setlist.setlist_created': (a) =>
    t('band_space_activity.setlist.setlist_created', { name: a.payload?.name ?? '—' }),
  'setlist.setlist_renamed': (a) =>
    t('band_space_activity.setlist.setlist_renamed', { name: a.payload?.name ?? '—' }),
  'setlist.setlist_duplicated': (a) =>
    t('band_space_activity.setlist.setlist_duplicated', { name: a.payload?.name ?? '—' }),
  'setlist.setlist_archived': (a) =>
    t('band_space_activity.setlist.setlist_archived', { name: a.payload?.name ?? '—' }),
  'setlist.setlist_unarchived': (a) =>
    t('band_space_activity.setlist.setlist_unarchived', { name: a.payload?.name ?? '—' }),

  // Setlist — items
  'setlist.setlist_item_added': (a) =>
    a.payload?.label
      ? t('band_space_activity.setlist.setlist_item_added', { label: a.payload.label })
      : t('band_space_activity.setlist.setlist_item_added_untitled'),
  'setlist.setlist_items_added': (a) => {
    const name = a.payload?.name ?? '—'
    if (a.payload?.count === 1) {
      return t('band_space_activity.setlist.setlist_items_added_one', { name })
    }
    if (a.payload?.count == null) {
      return t('band_space_activity.setlist.setlist_items_added_some', { name })
    }
    return t('band_space_activity.setlist.setlist_items_added', { count: a.payload.count, name })
  },
  'setlist.setlist_items_copied': (a) =>
    t('band_space_activity.setlist.setlist_items_copied', {
      source: a.payload?.source_name ?? '—',
      name: a.payload?.name ?? '—'
    }),
  'setlist.setlist_item_removed': () => t('band_space_activity.setlist.setlist_item_removed'),
  'setlist.setlist_item_reordered': (a) =>
    a.payload?.count
      ? t('band_space_activity.setlist.setlist_item_reordered_count', { count: a.payload.count })
      : t('band_space_activity.setlist.setlist_item_reordered'),
  'setlist.setlist_item_updated': () => t('band_space_activity.setlist.setlist_item_updated'),

  // Setlist — files
  'setlist.setlist_file_attached': (a) =>
    t('band_space_activity.setlist.setlist_file_attached', {
      name: a.payload?.original_name ?? '—'
    }),
  'setlist.setlist_file_detached': (a) =>
    t('band_space_activity.setlist.setlist_file_detached', {
      name: a.payload?.original_name ?? '—'
    }),

  // Tech riders
  'rider.rider_created': (a) =>
    t('band_space_activity.rider.rider_created', { name: a.payload?.name ?? '—' }),
  'rider.rider_renamed': (a) =>
    t('band_space_activity.rider.rider_renamed', { name: a.payload?.name ?? '—' }),
  'rider.rider_archived': (a) =>
    t('band_space_activity.rider.rider_archived', { name: a.payload?.name ?? '—' }),
  'rider.rider_unarchived': (a) =>
    t('band_space_activity.rider.rider_unarchived', { name: a.payload?.name ?? '—' }),
  'rider.rider_duplicated': (a) =>
    t('band_space_activity.rider.rider_duplicated', {
      source: a.payload?.source_name ?? '—',
      name: a.payload?.name ?? '—'
    }),
  'rider.rider_item_added': (a) =>
    t('band_space_activity.rider.rider_item_added', {
      title: a.payload?.title ?? '—',
      rider: a.payload?.rider_name ?? '—'
    }),
  'rider.rider_item_renamed': (a) =>
    t('band_space_activity.rider.rider_item_renamed', {
      rider: a.payload?.rider_name ?? '—',
      title: a.payload?.title ?? '—'
    }),
  'rider.rider_item_updated': (a) =>
    t('band_space_activity.rider.rider_item_updated', {
      title: a.payload?.title ?? '—',
      rider: a.payload?.rider_name ?? '—'
    }),
  'rider.rider_item_removed': (a) =>
    t('band_space_activity.rider.rider_item_removed', {
      title: a.payload?.title ?? '—',
      rider: a.payload?.rider_name ?? '—'
    }),
  'rider.rider_item_reordered': (a) =>
    a.payload?.count
      ? t('band_space_activity.rider.rider_item_reordered_count', {
          count: a.payload.count,
          rider: a.payload?.rider_name ?? '—'
        })
      : t('band_space_activity.rider.rider_item_reordered', {
          rider: a.payload?.rider_name ?? '—'
        }),
  'rider.rider_stage_plot_updated': (a) =>
    t('band_space_activity.rider.rider_stage_plot_updated', {
      title: a.payload?.title ?? '—',
      rider: a.payload?.rider_name ?? '—',
      count: a.payload?.element_count ?? 0
    }),
  'rider.rider_patch_list_updated': (a) =>
    t('band_space_activity.rider.rider_patch_list_updated', {
      title: a.payload?.title ?? '—',
      rider: a.payload?.rider_name ?? '—',
      inputs: a.payload?.input_count ?? 0,
      outputs: a.payload?.output_count ?? 0
    }),

  // Settings — band
  'settings.band_created': (a) =>
    t('band_space_activity.settings.band_created', { name: a.payload?.name ?? '—' }),
  'settings.band_renamed': (a) =>
    t('band_space_activity.settings.band_renamed', {
      from: raw(a.payload?.from),
      to: raw(a.payload?.to)
    }),

  // Settings — membership
  'settings.member_role_changed': (a) =>
    t('band_space_activity.settings.member_role_changed', {
      member: member(a.payload?.target_username),
      from: raw(a.payload?.from),
      to: raw(a.payload?.to)
    }),
  'settings.member_removed': (a) =>
    t('band_space_activity.settings.member_removed', {
      member: member(a.payload?.target_username)
    }),
  'settings.member_left': () => t('band_space_activity.settings.member_left'),
  'settings.member_profile_updated': (a) =>
    t('band_space_activity.settings.member_profile_updated', {
      member: member(a.payload?.target_username)
    }),

  // Settings — invitations
  'settings.invitation_sent': (a) =>
    t('band_space_activity.settings.invitation_sent', { invitee: invitee(a.payload) }),
  'settings.invitation_accepted': (a) =>
    t('band_space_activity.settings.invitation_accepted', { invitee: invitee(a.payload) }),
  'settings.invitation_declined': (a) =>
    t('band_space_activity.settings.invitation_declined', { invitee: invitee(a.payload) }),
  'settings.invitation_revoked': (a) =>
    t('band_space_activity.settings.invitation_revoked', { invitee: invitee(a.payload) }),
  'settings.invitation_expired': (a) =>
    t('band_space_activity.settings.invitation_expired', { invitee: invitee(a.payload) }),

  // Settings — suppression de l'espace
  'settings.deletion_scheduled': (a) =>
    a.payload?.scheduled_for
      ? t('band_space_activity.settings.deletion_scheduled_for', {
          date: formatDateLong(a.payload.scheduled_for)
        })
      : t('band_space_activity.settings.deletion_scheduled'),
  'settings.deletion_cancelled': () => t('band_space_activity.settings.deletion_cancelled')
}

export function activitySentence(activity) {
  const key = `${activity.module}.${activity.type}`
  const fn = SENTENCES[key]
  if (fn) {
    return fn(activity)
  }
  return t('band_space_activity.unknown', { type: activity.type, module: activity.module })
}
