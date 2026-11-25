/**
 * The emails offered at sign-up (#1445), by section, under their API names. The rows the settings page
 * also shows reuse its wording, so the two screens never drift apart.
 */
export const SIGNUP_EMAIL_GROUPS = Object.freeze([
  {
    titleKey: 'profile.settings.notifications.groups.musicall',
    rows: [
      {
        setting: 'marketing',
        labelKey: 'auth.register.emails.marketing_label',
        descriptionKey: 'auth.register.emails.marketing_description'
      },
      {
        setting: 'site_news',
        labelKey: 'profile.settings.notifications.rows.site_news.label',
        descriptionKey: 'auth.register.emails.site_news_description'
      },
      {
        setting: 'activity_reminder',
        labelKey: 'profile.settings.notifications.rows.activity_reminder.label',
        descriptionKey: 'profile.settings.notifications.rows.activity_reminder.description'
      },
      {
        setting: 'weekly_recap',
        labelKey: 'profile.settings.notifications.rows.weekly_recap.label',
        descriptionKey: 'profile.settings.notifications.rows.weekly_recap.description'
      }
    ]
  },
  {
    titleKey: 'auth.register.emails.notifications_group',
    rows: [
      {
        setting: 'message_received',
        labelKey: 'profile.settings.notifications.rows.message_received.label',
        descriptionKey: 'profile.settings.notifications.rows.message_received.description'
      },
      {
        setting: 'publication_comment',
        labelKey: 'profile.settings.notifications.rows.publication_comment.label',
        descriptionKey: 'profile.settings.notifications.rows.publication_comment.description'
      },
      {
        setting: 'forum_reply',
        labelKey: 'profile.settings.notifications.rows.forum_reply.label',
        descriptionKey: 'profile.settings.notifications.rows.forum_reply.description'
      }
    ]
  },
  {
    titleKey: 'profile.settings.notifications.groups.band_space',
    rows: [
      {
        setting: 'band_agenda_reminder',
        labelKey: 'profile.settings.notifications.rows.band_agenda_reminder.label',
        descriptionKey: 'profile.settings.notifications.rows.band_agenda_reminder.description'
      }
    ]
  }
])
