<template>
  <div class="flex flex-col items-center gap-0.5">
    <Button
      icon="pi pi-chevron-up"
      text
      rounded
      size="small"
      :severity="localUserVote === 1 ? 'success' : 'secondary'"
      :loading="isVoting"
      aria-label="J'aime"
      @click.prevent.stop="handleVote(1)"
    />
    <span class="text-sm font-semibold text-center">{{ score }}</span>
    <Button
      icon="pi pi-chevron-down"
      text
      rounded
      size="small"
      :severity="localUserVote === -1 ? 'danger' : 'secondary'"
      :loading="isVoting"
      aria-label="Je n'aime pas"
      @click.prevent.stop="handleVote(-1)"
    />
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import { computed, ref } from 'vue'
import publicationApi from '../../api/publication/publication.js'

const props = defineProps({
  slug: { type: String, required: true },
  upvotes: { type: Number, default: 0 },
  downvotes: { type: Number, default: 0 },
  userVote: { type: Number, default: null }
})

const localUpvotes = ref(props.upvotes)
const localDownvotes = ref(props.downvotes)
const localUserVote = ref(props.userVote)
const isVoting = ref(false)

const score = computed(() => localUpvotes.value - localDownvotes.value)

async function handleVote(value) {
  if (isVoting.value) return
  isVoting.value = true
  try {
    const data = await publicationApi.votePublication(props.slug, value)
    localUpvotes.value = data.upvotes
    localDownvotes.value = data.downvotes
    localUserVote.value = data.user_vote
  } finally {
    isVoting.value = false
  }
}
</script>
