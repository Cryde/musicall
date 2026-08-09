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

// Worded so they read correctly after « à » or « de » without contracting, which lets the attach,
// detach and source_deleted sentences share one list.
const SOURCE_NOUNS = {
  task: 'la tâche',
  finance: "l'entrée financière",
  note: 'la note',
  song: 'la chanson',
  setlist: 'la setlist'
}

function sourceNoun(activity) {
  return SOURCE_NOUNS[activity.payload?.source_type] ?? null
}

function quotedSourceLabel(activity) {
  const label = activity.payload?.source_label
  return label ? ` « ${label} »` : ''
}

const FILE_ACTIVITY_LABELS = {
  uploaded: () => 'a téléversé le fichier',
  // folder_path is only set when the file was swept up by a folder cascade: the folder is gone, so
  // the feed is the only place left saying where the file used to live.
  archived: (a) => {
    const path = a.payload?.folder_path
    return path ? `a archivé le fichier (dossier « ${path} » supprimé)` : 'a archivé le fichier'
  },
  restored: () => 'a restauré le fichier',
  purged: () => 'a supprimé définitivement le fichier',
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
    return tag ? `a ajouté le tag ${tag}` : 'a ajouté un tag'
  },
  untagged: (a) => {
    const tag = a.payload?.tag_name
    return tag ? `a retiré le tag ${tag}` : 'a retiré un tag'
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
    const noun = sourceNoun(a)
    return noun ? `a attaché le fichier à ${noun}${quotedSourceLabel(a)}` : 'a attaché le fichier'
  },
  detached: (a) => {
    const noun = sourceNoun(a)
    return noun ? `a détaché le fichier de ${noun}${quotedSourceLabel(a)}` : 'a détaché le fichier'
  },
  // The file was released because its source was deleted, not because somebody detached it: saying so
  // keeps the actor from being credited with an action they never took.
  source_deleted: (a) => {
    const noun = sourceNoun(a) ?? 'la ressource associée'
    return `a supprimé ${noun}${quotedSourceLabel(a)}, le fichier a été détaché`
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
