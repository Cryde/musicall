<template>
  <Card :id="`post-${post.id}`" class="mb-4">
    <template #content>
      <div class="flex flex-col md:flex-row gap-4">
        <div class="flex md:flex-col items-center gap-3 md:w-24 shrink-0">
          <Avatar
            v-if="post.creator.profile_picture?.small"
            :image="post.creator.profile_picture.small"
            size="large"
            shape="circle"
          />
          <Avatar
            v-else
            :label="post.creator.username.charAt(0).toUpperCase()"
            :style="getAvatarStyle(post.creator.username)"
            size="large"
            shape="circle"
          />
          <span class="font-medium text-sm">{{ post.creator.username }}</span>
          <Button
            v-if="canContact"
            icon="pi pi-envelope"
            size="small"
            severity="secondary"
            text
            rounded
            v-tooltip.bottom="'Contacter'"
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

const showMessageModal = ref(false)
const showAuthModal = ref(false)

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
