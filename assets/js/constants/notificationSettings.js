/** Every switch, under its API name, with the value it has when nothing was saved yet. */
export const NOTIFICATION_DEFAULTS = Object.freeze({
  site_news: true,
  weekly_recap: true,
  message_received: true,
  publication_comment: true,
  publication_vote: true,
  forum_reply: true,
  marketing: false,
  activity_reminder: true,
  band_agenda_reminder: true,
  show_online_presence: true,
  show_read_receipts: true,
  push_message_received: true,
  push_publication_comment: true,
  push_forum_reply: true,
  push_moderation: true,
  push_band_chat: true,
  push_band_mention: true,
  push_band_tasks: true,
  push_band_agenda: true,
  push_band_agenda_reminder: true,
  push_band_finance: true,
  push_band_membership: true
})

/** The channel columns, in order: the slot a row names its switch in, its header and what it means. */
export const CHANNELS = Object.freeze([
  {
    slot: 'email',
    columnKey: 'profile.settings.notifications.column_email',
    helpKey: 'profile.settings.notifications.column_email_help',
    ariaKey: 'profile.settings.notifications.email_aria_label'
  },
  {
    slot: 'push',
    columnKey: 'profile.settings.notifications.column_push',
    helpKey: 'profile.settings.notifications.column_push_help',
    ariaKey: 'profile.settings.notifications.push_aria_label'
  }
])

/**
 * The settings page (#1419): one row per event, grouped by module, with the channels as columns so
 * an event is listed once. A row names the switch behind each channel it has, and leaves out a
 * channel that does not exist for it. A `bell` row has one switch across both columns.
 */
export const NOTIFICATION_GROUPS = Object.freeze([
  {
    titleKey: 'profile.settings.notifications.groups.band_space',
    rows: [
      {
        labelKey: 'profile.settings.notifications.rows.band_chat.label',
        descriptionKey: 'profile.settings.notifications.rows.band_chat.description',
        push: 'push_band_chat'
      },
      {
        labelKey: 'profile.settings.notifications.rows.band_mention.label',
        descriptionKey: 'profile.settings.notifications.rows.band_mention.description',
        push: 'push_band_mention'
      },
      {
        labelKey: 'profile.settings.notifications.rows.band_tasks.label',
        descriptionKey: 'profile.settings.notifications.rows.band_tasks.description',
        push: 'push_band_tasks'
      },
      {
        labelKey: 'profile.settings.notifications.rows.band_agenda.label',
        descriptionKey: 'profile.settings.notifications.rows.band_agenda.description',
        push: 'push_band_agenda'
      },
      {
        labelKey: 'profile.settings.notifications.rows.band_agenda_reminder.label',
        descriptionKey: 'profile.settings.notifications.rows.band_agenda_reminder.description',
        email: 'band_agenda_reminder',
        push: 'push_band_agenda_reminder'
      },
      {
        labelKey: 'profile.settings.notifications.rows.band_finance.label',
        descriptionKey: 'profile.settings.notifications.rows.band_finance.description',
        push: 'push_band_finance'
      },
      {
        labelKey: 'profile.settings.notifications.rows.band_membership.label',
        descriptionKey: 'profile.settings.notifications.rows.band_membership.description',
        push: 'push_band_membership'
      }
    ]
  },
  {
    titleKey: 'profile.settings.notifications.groups.messages',
    rows: [
      {
        labelKey: 'profile.settings.notifications.rows.message_received.label',
        descriptionKey: 'profile.settings.notifications.rows.message_received.description',
        email: 'message_received',
        push: 'push_message_received'
      }
    ]
  },
  {
    titleKey: 'profile.settings.notifications.groups.publications',
    rows: [
      {
        labelKey: 'profile.settings.notifications.rows.publication_comment.label',
        descriptionKey: 'profile.settings.notifications.rows.publication_comment.description',
        email: 'publication_comment',
        push: 'push_publication_comment'
      },
      {
        labelKey: 'profile.settings.notifications.rows.publication_vote.label',
        descriptionKey: 'profile.settings.notifications.rows.publication_vote.description',
        bell: 'publication_vote'
      }
    ]
  },
  {
    titleKey: 'profile.settings.notifications.groups.forum',
    rows: [
      {
        labelKey: 'profile.settings.notifications.rows.forum_reply.label',
        descriptionKey: 'profile.settings.notifications.rows.forum_reply.description',
        email: 'forum_reply',
        push: 'push_forum_reply'
      }
    ]
  },
  {
    titleKey: 'profile.settings.notifications.groups.moderation',
    rows: [
      {
        labelKey: 'profile.settings.notifications.rows.moderation.label',
        descriptionKey: 'profile.settings.notifications.rows.moderation.description',
        push: 'push_moderation'
      }
    ]
  },
  {
    titleKey: 'profile.settings.notifications.groups.musicall',
    rows: [
      {
        labelKey: 'profile.settings.notifications.rows.site_news.label',
        descriptionKey: 'profile.settings.notifications.rows.site_news.description',
        email: 'site_news'
      },
      {
        labelKey: 'profile.settings.notifications.rows.weekly_recap.label',
        descriptionKey: 'profile.settings.notifications.rows.weekly_recap.description',
        email: 'weekly_recap'
      },
      {
        labelKey: 'profile.settings.notifications.rows.activity_reminder.label',
        descriptionKey: 'profile.settings.notifications.rows.activity_reminder.description',
        email: 'activity_reminder'
      },
      {
        labelKey: 'profile.settings.notifications.rows.marketing.label',
        descriptionKey: 'profile.settings.notifications.rows.marketing.description',
        email: 'marketing'
      }
    ]
  }
])

/**
 * The privacy switches of the chat (#1040, #1243): saved with the notifications, but not about them,
 * so they have no channel columns.
 */
export const PRIVACY_SETTINGS = Object.freeze([
  {
    setting: 'show_online_presence',
    labelKey: 'profile.settings.notifications.rows.show_online_presence.label',
    descriptionKey: 'profile.settings.notifications.rows.show_online_presence.description'
  },
  {
    setting: 'show_read_receipts',
    labelKey: 'profile.settings.notifications.rows.show_read_receipts.label',
    descriptionKey: 'profile.settings.notifications.rows.show_read_receipts.description'
  }
])
