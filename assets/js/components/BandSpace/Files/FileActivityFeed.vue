<template>
  <div v-if="activities.length > 0" class="flex flex-col gap-3">
    <h4 class="text-sm font-semibold text-surface-700 dark:text-surface-200">Activité</h4>
    <div class="flex flex-col gap-2 border-l-2 border-surface-200 dark:border-surface-700 pl-3">
      <div
        v-for="activity in activities"
        :key="activity.id"
        class="flex items-start gap-2 text-xs text-surface-500 dark:text-surface-400"
      >
        <Avatar
          :username="activity.actor_username"
          :picture-url="activity.actor_profile_picture_url"
          size="sm"
        />
        <div class="flex-1 min-w-0">
          <span class="font-medium text-surface-700 dark:text-surface-200">
            {{ activity.actor_username }}
          </span>
          {{ activityLabel(activity) }}
          <span class="text-surface-400 ml-1">{{ formatRelative(activity.creation_datetime) }}</span>
        </div>
      </div>
    </div>
  </div>
  <p v-else class="text-xs italic text-surface-400">Aucune activité pour ce fichier.</p>
</template>

<script setup>
import { formatDistanceToNow } from 'date-fns'
import { fr } from 'date-fns/locale'
import Avatar from '../../User/Avatar.vue'

defineProps({
  activities: { type: Array, default: () => [] }
})

const FILE_ACTIVITY_LABELS = {
  uploaded: () => 'a téléversé le fichier',
  archived: () => 'a archivé le fichier',
  restored: () => 'a restauré le fichier',
  renamed: (a) => {
    const from = a.payload?.from
    const to = a.payload?.to
    if (from && to) return `a renommé ${from} en ${to}`
    return 'a renommé le fichier'
  },
  moved: (a) => {
    const to = a.payload?.to_folder_name
    return to ? `a déplacé le fichier vers ${to}` : 'a déplacé le fichier'
  },
  tagged: (a) => {
    const tag = a.payload?.tag_name
    return tag ? `a ajouté l'étiquette ${tag}` : 'a ajouté une étiquette'
  },
  untagged: (a) => {
    const tag = a.payload?.tag_name
    return tag ? `a retiré l'étiquette ${tag}` : 'a retiré une étiquette'
  },
  version_added: (a) => {
    const num = a.payload?.version_number
    return num ? `a ajouté la version ${num}` : 'a ajouté une nouvelle version'
  },
  rolled_back: (a) => {
    const num = a.payload?.version_number
    return num ? `est revenu à la version ${num}` : 'est revenu à une version antérieure'
  },
  shared: () => 'a créé un lien de partage',
  share_revoked: () => 'a révoqué un lien de partage',
  public_accessed: () => 'le lien public a été consulté',
  attached: (a) => {
    const type = a.payload?.source_type
    const label = a.payload?.source_label
    if (type === 'task')
      return label ? `a attaché le fichier à la tâche ${label}` : 'a attaché le fichier à une tâche'
    if (type === 'finance')
      return label
        ? `a attaché le fichier à l'entrée ${label}`
        : 'a attaché le fichier à une entrée financière'
    return 'a attaché le fichier'
  },
  detached: (a) => {
    const type = a.payload?.source_type
    if (type === 'task') return 'a détaché le fichier de la tâche'
    if (type === 'finance') return "a détaché le fichier de l'entrée financière"
    return 'a détaché le fichier'
  }
}

function activityLabel(activity) {
  const factory = FILE_ACTIVITY_LABELS[activity.type]
  return factory ? factory(activity) : activity.type
}

function formatRelative(dateStr) {
  return formatDistanceToNow(new Date(dateStr), { addSuffix: true, locale: fr })
}
</script>
