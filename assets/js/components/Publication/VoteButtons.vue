<template>
  <div class="flex items-center gap-1">
    <Button
      icon="pi pi-thumbs-up"
      text
      rounded
      size="small"
      :severity="userVote === 1 ? 'success' : 'secondary'"
      :loading="isVoting"
      v-tooltip.bottom="'J\'aime'"
      aria-label="J'aime"
      @click="handleVote(1)"
    />
    <span class="text-sm font-semibold min-w-6 text-center">{{ score }}</span>
    <Button
      icon="pi pi-thumbs-down"
      text
      rounded
      size="small"
      :severity="userVote === -1 ? 'danger' : 'secondary'"
      :loading="isVoting"
      v-tooltip.bottom="'Je n\'aime pas'"
      aria-label="Je n'aime pas"
      @click="handleVote(-1)"
    />
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import { computed } from 'vue'
import { storeToRefs } from 'pinia'
import { usePublicationStore } from '../../store/publication/publication.js'

const props = defineProps({
  slug: { type: String, required: true }
})

const publicationStore = usePublicationStore()
const { publication, isVoting } = storeToRefs(publicationStore)

const userVote = computed(() => publication.value?.user_vote ?? null)
const score = computed(() => {
  const up = publication.value?.upvotes ?? 0
  const down = publication.value?.downvotes ?? 0
  return up - down
})

function handleVote(value) {
  publicationStore.vote(props.slug, value)
}
</script>
