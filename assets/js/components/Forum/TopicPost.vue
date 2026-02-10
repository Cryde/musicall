<template>
  <Card :id="`post-${post.id}`" class="mb-4">
    <template #content>
      <div class="flex flex-col md:flex-row gap-4">
        <div class="flex md:flex-col items-center gap-3 md:w-24 shrink-0">
          <Avatar
            v-if="post.creator.profile_picture?.small && !post.creator.deletion_datetime"
            :image="post.creator.profile_picture.small"
            :pt="{ image: { alt: `Photo de ${creatorName}` } }"
            size="large"
            shape="circle"
            role="img"
            :aria-label="`Photo de ${creatorName}`"
          />
          <Avatar
            v-else
            :label="creatorName.charAt(0).toUpperCase()"
            :style="getAvatarStyle(creatorName)"
            size="large"
            shape="circle"
            role="img"
            :aria-label="`Avatar de ${creatorName}`"
          />
          <router-link
            v-if="!post.creator.deletion_datetime"
            :to="{ name: 'app_user_public_profile', params: { username: post.creator.username } }"
            class="font-medium text-sm hover:text-primary transition-colors"
            :aria-label="`Voir le profil de ${creatorName}`"
          >{{ creatorName }}</router-link>
          <span v-else class="font-medium text-sm text-surface-500">{{ creatorName }}</span>
          <Button
            v-if="canContact && !post.creator.deletion_datetime"
            icon="pi pi-envelope"
            size="small"
            severity="secondary"
            text
            rounded
            v-tooltip.bottom="'Contacter'"
            :aria-label="`Contacter ${creatorName}`"
            @click="handleContact"
          />
        </div>

        <div class="flex-1">
          <div class="text-sm text-surface-500 dark:text-surface-400 mb-3">
            {{ formatDate(post.creation_datetime) }}
            <span v-if="post.update_datetime" class="ml-2 italic">
              (modifié le {{ formatDate(post.update_datetime) }})
            </span>
          </div>

          <div class="prose dark:prose-invert max-w-none" v-html="post.content" />
          <div class="flex items-center gap-1 mt-3">
            <Button
              icon="pi pi-thumbs-up"
              text
              rounded
              size="small"
              :severity="localUserVote === 1 ? 'success' : 'secondary'"
              :loading="isVoting"
              aria-label="J'aime"
              @click="handleVote(1)"
            />
            <span class="text-sm font-semibold min-w-6 text-center">{{ score }}</span>
            <Button
              icon="pi pi-thumbs-down"
              text
              rounded
              size="small"
              :severity="localUserVote === -1 ? 'danger' : 'secondary'"
              :loading="isVoting"
              aria-label="Je n'aime pas"
              @click="handleVote(-1)"
            />
          </div>
        </div>
      </div>
    </template>
  </Card>

  <SendMessageModal
    v-model:visible="showMessageModal"
    :selected-recipient="post.creator"
  />

  <AuthRequiredModal
    v-model:visible="showAuthModal"
    message="Vous devez vous connecter pour envoyer un message."
  />
</template>

<script setup>
import Avatar from 'primevue/avatar'
import Button from 'primevue/button'
import Card from 'primevue/card'
import { computed, ref } from 'vue'
import forumApi from '../../api/forum/forum.js'
import { displayName } from '../../helper/user/displayName.js'
import { useUserSecurityStore } from '../../store/user/security.js'
import { getAvatarStyle } from '../../utils/avatar.js'
import { formatDate } from '../../utils/date.js'
import AuthRequiredModal from '../Auth/AuthRequiredModal.vue'
import SendMessageModal from '../Message/SendMessageModal.vue'

const props = defineProps({
  post: {
    type: Object,
    required: true
  }
})

const userSecurityStore = useUserSecurityStore()

const creatorName = computed(() => displayName(props.post.creator))

const showMessageModal = ref(false)
const showAuthModal = ref(false)

const localUpvotes = ref(props.post.upvotes ?? 0)
const localDownvotes = ref(props.post.downvotes ?? 0)
const localUserVote = ref(props.post.user_vote ?? null)
const isVoting = ref(false)

const score = computed(() => localUpvotes.value - localDownvotes.value)

async function handleVote(value) {
  if (isVoting.value) return
  isVoting.value = true
  try {
    const data = await forumApi.voteForumPost(props.post.id, value)
    localUpvotes.value = data.upvotes
    localDownvotes.value = data.downvotes
    localUserVote.value = data.user_vote
  } finally {
    isVoting.value = false
  }
}

const canContact = computed(() => {
  // Don't show button if not logged in or if it's the current user's post
  if (!userSecurityStore.isAuthenticated) return true
  return userSecurityStore.user?.id !== props.post.creator.id
})

function handleContact() {
  if (!userSecurityStore.isAuthenticated) {
    showAuthModal.value = true
    return
  }
  showMessageModal.value = true
}
</script>
