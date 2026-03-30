<template>
  <div v-if="activities.length > 0" class="flex flex-col gap-3">
    <h4 class="text-sm font-semibold text-surface-700 dark:text-surface-200">Activité</h4>
    <div class="flex flex-col gap-2 border-l-2 border-surface-200 dark:border-surface-700 pl-3">
      <div
        v-for="activity in activities"
        :key="activity.id"
        class="text-xs text-surface-500 dark:text-surface-400"
      >
        <span class="font-medium text-surface-700 dark:text-surface-200">
          {{ activity.actor_username }}
        </span>
        {{ activityLabel(activity) }}
        <span class="text-surface-400 ml-1">{{ formatRelative(activity.creation_datetime) }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { formatDistanceToNow } from 'date-fns'
import { fr } from 'date-fns/locale'

defineProps({
  activities: { type: Array, default: () => [] }
})

function activityLabel(activity) {
  const labels = {
    status_changed: () => {
      const from = statusLabel(activity.payload?.from)
      const to = statusLabel(activity.payload?.to)
      return `a changé le statut de ${from} à ${to}`
    },
    assignee_added: () => `a ajouté ${activity.payload?.assignee_username || 'un membre'}`,
    assignee_removed: () => `a retiré ${activity.payload?.assignee_username || 'un membre'}`,
    category_changed: () => 'a modifié la catégorie',
    due_date_changed: () => 'a modifié la date d\'échéance',
    comment_added: () => 'a ajouté un commentaire',
    mention: () => `a mentionné ${activity.payload?.mentioned_username || 'un membre'}`
  }
  return (labels[activity.type] || (() => activity.type))()
}

function statusLabel(status) {
  const map = { todo: 'À faire', in_progress: 'En cours', done: 'Terminé' }
  return map[status] || status
}

function formatRelative(dateStr) {
  return formatDistanceToNow(new Date(dateStr), { addSuffix: true, locale: fr })
}
</script>
