<template>
  <div v-if="activities.length > 0" class="flex flex-col gap-3">
    <h4 class="text-sm font-semibold text-surface-700 dark:text-surface-200">
      {{ t('band_space_files.activity.title') }}
    </h4>
    <div class="flex flex-col gap-2 border-l-2 border-surface-200 dark:border-surface-700 pl-3">
      <div
        v-for="activity in activities"
        :key="activity.id"
        class="flex items-start gap-2 text-xs text-surface-500 dark:text-surface-400"
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
  <p v-else class="text-xs italic text-surface-400">{{ t('band_space_files.activity.empty') }}</p>
</template>

<script setup>
import { formatDistanceToNow } from 'date-fns'
import { useI18n } from 'vue-i18n'
import { fileSourceDefiniteNoun } from '../../../constants/fileSources.js'
import { dateFnsLocale } from '../../../i18n/formatLocale.js'
import Avatar from '../../User/Avatar.vue'
import UserName from '../../User/UserName.vue'

defineProps({
  activities: { type: Array, default: () => [] }
})

const { t } = useI18n()

// The shared nouns are worded so they read correctly after « à » or « de » without contracting,
// which lets the attach, detach and source_deleted sentences share one list.
function sourceNoun(activity) {
  return fileSourceDefiniteNoun(activity.payload?.source_type)
}

// Each sentence is a catalogue message with the actor as its {actor} slot; the variant depends on
// which details the payload carries. The source's own label, when there is one, is quoted after it.
function sourceSentence(activity, keys, noun) {
  const label = activity.payload?.source_label
  return label
    ? { keypath: keys.labelled, params: { source: noun, label } }
    : { keypath: keys.plain, params: { source: noun } }
}

const FILE_ACTIVITY_SENTENCES = {
  uploaded: () => ({ keypath: 'band_space_files.activity.uploaded' }),
  // folder_path is only set when the file was swept up by a folder cascade: the folder is gone, so
  // the feed is the only place left saying where the file used to live.
  archived: (a) => {
    const path = a.payload?.folder_path
    return path
      ? { keypath: 'band_space_files.activity.archived_with_folder', params: { path } }
      : { keypath: 'band_space_files.activity.archived' }
  },
  restored: () => ({ keypath: 'band_space_files.activity.restored' }),
  purged: () => ({ keypath: 'band_space_files.activity.purged' }),
  renamed: (a) => {
    const from = a.payload?.from
    const to = a.payload?.to
    if (from && to)
      return { keypath: 'band_space_files.activity.renamed_from_to', params: { from, to } }
    return { keypath: 'band_space_files.activity.renamed' }
  },
  moved: (a) => {
    const to = a.payload?.to_folder_name
    return to
      ? { keypath: 'band_space_files.activity.moved_to', params: { folder: to } }
      : { keypath: 'band_space_files.activity.moved' }
  },
  tagged: (a) => {
    const tag = a.payload?.tag_name
    return tag
      ? { keypath: 'band_space_files.activity.tagged_named', params: { tag } }
      : { keypath: 'band_space_files.activity.tagged' }
  },
  untagged: (a) => {
    const tag = a.payload?.tag_name
    return tag
      ? { keypath: 'band_space_files.activity.untagged_named', params: { tag } }
      : { keypath: 'band_space_files.activity.untagged' }
  },
  version_added: (a) => {
    const num = a.payload?.version_number
    return num
      ? { keypath: 'band_space_files.activity.version_added_number', params: { number: num } }
      : { keypath: 'band_space_files.activity.version_added' }
  },
  rolled_back: (a) => {
    const num = a.payload?.version_number
    return num
      ? { keypath: 'band_space_files.activity.rolled_back_number', params: { number: num } }
      : { keypath: 'band_space_files.activity.rolled_back' }
  },
  shared: () => ({ keypath: 'band_space_files.activity.shared' }),
  share_revoked: () => ({ keypath: 'band_space_files.activity.share_revoked' }),
  public_accessed: () => ({ keypath: 'band_space_files.activity.public_accessed' }),
  attached: (a) => {
    const noun = sourceNoun(a)
    if (!noun) return { keypath: 'band_space_files.activity.attached' }
    return sourceSentence(
      a,
      {
        plain: 'band_space_files.activity.attached_to',
        labelled: 'band_space_files.activity.attached_to_labelled'
      },
      noun
    )
  },
  detached: (a) => {
    const noun = sourceNoun(a)
    if (!noun) return { keypath: 'band_space_files.activity.detached' }
    return sourceSentence(
      a,
      {
        plain: 'band_space_files.activity.detached_from',
        labelled: 'band_space_files.activity.detached_from_labelled'
      },
      noun
    )
  },
  // The file was released because its source was deleted, not because somebody detached it: saying so
  // keeps the actor from being credited with an action they never took.
  source_deleted: (a) =>
    sourceSentence(
      a,
      {
        plain: 'band_space_files.activity.source_deleted',
        labelled: 'band_space_files.activity.source_deleted_labelled'
      },
      sourceNoun(a) ?? t('band_space_files.activity.associated_resource')
    )
}

function activitySentence(activity) {
  const factory = FILE_ACTIVITY_SENTENCES[activity.type]
  const sentence = factory
    ? factory(activity)
    : { keypath: 'band_space_files.activity.unknown', params: { type: activity.type } }
  return { params: {}, ...sentence }
}

function formatRelative(dateStr) {
  return formatDistanceToNow(new Date(dateStr), { addSuffix: true, locale: dateFnsLocale() })
}
</script>
