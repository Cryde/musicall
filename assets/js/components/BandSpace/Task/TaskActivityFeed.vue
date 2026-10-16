<template>
  <div v-if="activities.length > 0" class="flex flex-col gap-3">
    <h4 class="text-sm font-semibold text-surface-700 dark:text-surface-200">{{ t('band_space_tasks.activity_feed.title') }}</h4>
    <div class="flex flex-col gap-2 border-l-2 border-surface-200 dark:border-surface-700 pl-3">
      <div
        v-for="activity in activities"
        :key="activity.id"
        class="flex items-center gap-2 text-xs text-surface-500 dark:text-surface-400"
      >
        <Avatar
          :username="activity.actor_username"
          :display-name="activity.actor_display_name"
          :picture-url="activity.actor_profile_picture_url"
          size="sm"
        />
        <div class="flex-1 min-w-0">
          <i18n-t :keypath="activitySentence(activity).keypath">
            <template #actor>
              <span class="font-medium text-surface-700 dark:text-surface-200">
                <UserName :username="activity.actor_username" :display-name="activity.actor_display_name" :picture-url="activity.actor_profile_picture_url" />
              </span>
            </template>
            <template v-for="(value, name) in activitySentence(activity).params" #[name]>{{ value }}</template>
          </i18n-t> <span class="text-surface-400 ml-1">{{ formatRelative(activity.creation_datetime) }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { formatDistanceToNow } from 'date-fns'
import { useI18n } from 'vue-i18n'
import { dateFnsLocale } from '../../../i18n/formatLocale.js'
import Avatar from '../../User/Avatar.vue'
import UserName from '../../User/UserName.vue'

defineProps({
  activities: { type: Array, default: () => [] }
})

const { t } = useI18n()

const STATUS_LABEL_KEYS = {
  todo: 'band_space_tasks.status.todo',
  in_progress: 'band_space_tasks.status.in_progress',
  done: 'band_space_tasks.status.done'
}

// Each sentence is a catalogue message with the actor as its {actor} slot.
function activitySentence(activity) {
  const sentences = {
    status_changed: () => ({
      keypath: 'band_space_tasks.activity_feed.status_changed',
      params: {
        from: statusLabel(activity.payload?.from),
        to: statusLabel(activity.payload?.to)
      }
    }),
    assignee_added: () => {
      const username = activity.payload?.assignee_username
      return username
        ? { keypath: 'band_space_tasks.activity_feed.assignee_added', params: { username } }
        : { keypath: 'band_space_tasks.activity_feed.assignee_added_unknown' }
    },
    assignee_removed: () => {
      const username = activity.payload?.assignee_username
      return username
        ? { keypath: 'band_space_tasks.activity_feed.assignee_removed', params: { username } }
        : { keypath: 'band_space_tasks.activity_feed.assignee_removed_unknown' }
    },
    category_changed: () => ({ keypath: 'band_space_tasks.activity_feed.category_changed' }),
    due_date_changed: () => ({ keypath: 'band_space_tasks.activity_feed.due_date_changed' }),
    comment_added: () => ({ keypath: 'band_space_tasks.activity_feed.comment_added' }),
    comment_edited: () => ({ keypath: 'band_space_tasks.activity_feed.comment_edited' }),
    comment_deleted: () => ({ keypath: 'band_space_tasks.activity_feed.comment_deleted' }),
    mention: () => {
      const username = activity.payload?.mentioned_username
      return username
        ? { keypath: 'band_space_tasks.activity_feed.mention', params: { username } }
        : { keypath: 'band_space_tasks.activity_feed.mention_unknown' }
    }
  }
  const sentence = sentences[activity.type]?.() ?? {
    keypath: 'band_space_tasks.activity_feed.unknown',
    params: { type: activity.type }
  }
  return { params: {}, ...sentence }
}

function statusLabel(status) {
  return STATUS_LABEL_KEYS[status] ? t(STATUS_LABEL_KEYS[status]) : status
}

function formatRelative(dateStr) {
  return formatDistanceToNow(new Date(dateStr), { addSuffix: true, locale: dateFnsLocale() })
}
</script>
