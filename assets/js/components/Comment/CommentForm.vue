<template>
  <div v-if="userSecurityStore.isAuthenticated" class="flex gap-4">
    <div class="shrink-0">
      <Avatar
        v-if="userSecurityStore.profilePictureUrl"
        :image="userSecurityStore.profilePictureUrl"
        shape="circle"
        size="large"
      />
      <Avatar
        v-else
        :label="userSecurityStore.user?.username?.charAt(0).toUpperCase()"
        shape="circle"
        size="large"
      />
    </div>
    <div class="flex-1 flex flex-col gap-3">
      <Message v-if="error" severity="error" :closable="false">
        {{ error }}
      </Message>

      <Textarea
        v-model="content"
        placeholder="Ajouter un commentaire..."
        rows="3"
        :disabled="commentStore.isPosting"
        class="w-full"
      />

      <div class="flex justify-end">
        <Button
          label="Poster le commentaire"
          icon="pi pi-send"
          :loading="commentStore.isPosting"
          :disabled="!canPost"
          @click="handleSubmit"
        />
      </div>
    </div>
  </div>

  <div v-else class="text-center py-4 text-surface-600 dark:text-surface-400">
    Vous devez
    <RouterLink :to="{ name: 'app_login' }" class="text-primary font-medium">
      être connecté
    </RouterLink>
    ou
    <RouterLink :to="{ name: 'app_register' }" class="text-primary font-medium">
      inscrit
    </RouterLink>
    pour pouvoir poster un commentaire
  </div>
</template>

<script setup>
import Avatar from 'primevue/avatar'
import Button from 'primevue/button'
import Message from 'primevue/message'
import Textarea from 'primevue/textarea'
import { computed, ref } from 'vue'
import { useCommentStore } from '../../store/comment/comment.js'
import { useUserSecurityStore } from '../../store/user/security.js'

const userSecurityStore = useUserSecurityStore()
const commentStore = useCommentStore()

const content = ref('')
const error = ref('')

const canPost = computed(() => {
  return content.value.trim().length > 0 && !commentStore.isPosting
})

async function handleSubmit() {
  error.value = ''

  try {
    await commentStore.postComment(content.value)
    content.value = ''
  } catch (e) {
    error.value = e.message || 'Une erreur est survenue'
  }
}
</script>
