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
        <div class="flex items-center justify-center w-10 h-10 rounded-full bg-red-100 dark:bg-red-900/30">
          <i class="pi pi-flag text-red-600 dark:text-red-400 text-lg" />
        </div>
        <div>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 m-0">
            Signaler ce message
          </h2>
          <p class="text-sm text-surface-500 dark:text-surface-400 m-0">
            Expliquez pourquoi ce message pose problème
          </p>
        </div>
      </div>
    </template>

    <div class="flex flex-col gap-2">
      <label for="report-reason" class="text-sm font-medium text-surface-700 dark:text-surface-200">
        Motif du signalement
      </label>
      <Textarea
        id="report-reason"
        v-model="reason"
        :disabled="isSubmitting"
        :invalid="isTooLong"
        rows="5"
        fluid
        placeholder="Propos insultants, spam, contenu hors sujet..."
      />
      <div class="flex justify-end text-xs" :class="isTooLong ? 'text-red-500' : 'text-surface-500'">
        {{ reason.length }} / {{ MAX_REASON_LENGTH }}
      </div>

      <Message v-if="errorMessage" severity="error" :closable="false">
        {{ errorMessage }}
      </Message>
    </div>

    <template #footer>
      <div class="flex justify-end gap-2 w-full">
        <Button label="Annuler" severity="secondary" text :disabled="isSubmitting" @click="visible = false" />
        <Button
          label="Signaler"
          icon="pi pi-flag"
          severity="danger"
          :loading="isSubmitting"
          :disabled="!canSubmit"
          class="px-5"
          @click="submitReport"
        />
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import Message from 'primevue/message'
import Textarea from 'primevue/textarea'
import { computed, ref } from 'vue'
import forumApi from '../../api/forum/forum.js'

const MAX_REASON_LENGTH = 500

const props = defineProps({
  postId: { type: String, required: true }
})

const emit = defineEmits(['reported'])
const visible = defineModel('visible', { type: Boolean, default: false })

const reason = ref('')
const isSubmitting = ref(false)
const errorMessage = ref('')

const isTooLong = computed(() => reason.value.length > MAX_REASON_LENGTH)

const canSubmit = computed(
  () => reason.value.trim().length > 0 && !isTooLong.value && !isSubmitting.value
)

async function submitReport() {
  if (!canSubmit.value) return

  errorMessage.value = ''
  isSubmitting.value = true
  try {
    await forumApi.reportForumPost(props.postId, reason.value.trim())
    visible.value = false
    emit('reported')
  } catch (e) {
    errorMessage.value = e.message || 'Une erreur est survenue.'
  } finally {
    isSubmitting.value = false
  }
}

function reset() {
  reason.value = ''
  errorMessage.value = ''
  isSubmitting.value = false
}
</script>
