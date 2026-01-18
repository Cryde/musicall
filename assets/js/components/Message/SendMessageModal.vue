<template>
  <Dialog
    v-model:visible="visible"
    modal
    :style="{ width: '500px' }"
    :pt="{
      header: { class: 'pb-0 border-0' },
      content: { class: 'pt-4' }
    }"
    @hide="reset"
  >
    <template #header>
      <div class="flex items-center gap-3">
        <div class="flex items-center justify-center w-10 h-10 rounded-full bg-primary-100 dark:bg-primary-900/30">
          <i class="pi pi-envelope text-primary-600 dark:text-primary-400 text-lg" />
        </div>
        <div>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 m-0">
            Nouveau message
          </h2>
          <p class="text-sm text-surface-500 dark:text-surface-400 m-0">
            Envoyez un message privé
          </p>
        </div>
      </div>
    </template>

    <div class="flex flex-col gap-4">
      <!-- Recipient selection -->
      <div v-if="!selectedRecipient">
        <AutoComplete
          v-model="recipient"
          :suggestions="recipientsOptions"
          :disabled="isSending"
          optionLabel="username"
          placeholder="Rechercher un destinataire..."
          fluid
          @complete="searchUsers"
        >
          <template #option="{ option }">
            <div class="flex items-center gap-3 py-1">
              <Avatar
                v-if="option.profile_picture?.small"
                :image="option.profile_picture.small"
                :pt="{ image: { alt: `Photo de ${option.username}` } }"
                shape="circle"
                size="normal"
                role="img"
                :aria-label="`Photo de ${option.username}`"
              />
              <Avatar
                v-else
                :label="option.username.charAt(0).toUpperCase()"
                :style="getAvatarStyle(option.username)"
                shape="circle"
                size="normal"
                role="img"
                :aria-label="`Avatar de ${option.username}`"
              />
              <div class="font-medium">{{ option.username }}</div>
            </div>
          </template>
        </AutoComplete>
      </div>

      <!-- Selected recipient card -->
      <div v-else class="flex items-center gap-3 p-3 bg-gradient-to-r from-primary-50 to-primary-100/50 dark:from-primary-900/20 dark:to-primary-800/10 rounded-lg border border-primary-200 dark:border-primary-800">
        <Avatar
          v-if="selectedRecipient.profile_picture?.small"
          :image="selectedRecipient.profile_picture.small"
          :pt="{ image: { alt: `Photo de ${selectedRecipient.username}` } }"
          shape="circle"
          size="large"
          role="img"
          :aria-label="`Photo de ${selectedRecipient.username}`"
        />
        <Avatar
          v-else
          :label="selectedRecipient.username.charAt(0).toUpperCase()"
          :style="getAvatarStyle(selectedRecipient.username)"
          shape="circle"
          size="large"
          role="img"
          :aria-label="`Avatar de ${selectedRecipient.username}`"
        />
        <div class="flex-1">
          <div class="font-semibold text-surface-900 dark:text-surface-0">{{ selectedRecipient.username }}</div>
        </div>
        <i class="pi pi-check-circle text-primary-500 text-xl" />
      </div>

      <!-- Message input -->
      <Textarea
        v-model="content"
        :disabled="isSending"
        rows="5"
        fluid
        placeholder="Écrivez votre message..."
      />

      <!-- Error message -->
      <Message v-if="errorMessage" severity="error" :closable="false">
        {{ errorMessage }}
      </Message>
    </div>

    <template #footer>
      <div class="flex justify-end gap-2 w-full">
        <Button
          label="Annuler"
          severity="secondary"
          text
          @click="visible = false"
        />
        <Button
          label="Envoyer le message"
          icon="pi pi-send"
          :loading="isSending"
          :disabled="!canSend"
          class="px-5"
          @click="sendMessage"
        />
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import { useDebounceFn } from '@vueuse/core'
import AutoComplete from 'primevue/autocomplete'
import Avatar from 'primevue/avatar'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import Message from 'primevue/message'
import Textarea from 'primevue/textarea'
import { trackUmamiEvent } from '@jaseeey/vue-umami-plugin'
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import userSearchApi from '../../api/search/user.js'
import { useMessageStore } from '../../store/message/message.js'
import { getAvatarStyle } from '../../utils/avatar.js'

const props = defineProps({
  selectedRecipient: {
    type: Object,
    default: null
  }
})

const visible = defineModel('visible', { type: Boolean, default: false })

const router = useRouter()
const messageStore = useMessageStore()

const recipient = ref(null)
const content = ref('')
const recipientsOptions = ref([])
const isSending = ref(false)
const errorMessage = ref('')

const canSend = computed(() => {
  const hasRecipient = props.selectedRecipient || recipient.value
  return content.value.trim().length > 0 && hasRecipient && !isSending.value
})

watch(
  () => props.selectedRecipient,
  (newVal) => {
    recipient.value = newVal
  },
  { immediate: true }
)

const debouncedSearch = useDebounceFn(async (query) => {
  try {
    recipientsOptions.value = await userSearchApi.searchUsers(query)
  } catch (e) {
    console.error('Failed to search users:', e)
    recipientsOptions.value = []
  }
}, 300)

function searchUsers(event) {
  const query = event.query
  if (query.length >= 3) {
    debouncedSearch(query)
  }
}

async function sendMessage() {
  const targetRecipient = props.selectedRecipient || recipient.value
  if (!targetRecipient || !content.value.trim()) return

  errorMessage.value = ''
  isSending.value = true
  try {
    const threadId = await messageStore.postMessage({
      recipientId: targetRecipient.id,
      content: content.value
    })
    trackUmamiEvent('message-send')

    // Close modal and navigate to the thread
    visible.value = false
    if (threadId) {
      router.push({ name: 'app_messages', params: { threadId } })
    }
  } catch (e) {
    errorMessage.value = e.message || "Une erreur est survenue lors de l'envoi du message."
  } finally {
    isSending.value = false
  }
}

function reset() {
  isSending.value = false
  content.value = ''
  recipient.value = null
  recipientsOptions.value = []
  errorMessage.value = ''
}
</script>
