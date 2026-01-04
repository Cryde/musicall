<template>
  <Dialog
    v-model:visible="visibleModel"
    modal
    header="Ajouter un nouveau sujet"
    :style="{ width: '50rem' }"
    :breakpoints="{ '1199px': '75vw', '575px': '90vw' }"
  >
    <template v-if="!isSent">
      <div class="flex flex-col gap-4">
        <div class="flex flex-col gap-2">
          <label for="title" class="font-medium">Titre du sujet</label>
          <InputText
            id="title"
            v-model="title"
            placeholder="Votre titre ici"
            :invalid="title.length > 0 && title.length < MIN_TITLE_LENGTH"
          />
          <small v-if="title.length > 0 && title.length < MIN_TITLE_LENGTH" class="text-red-500">
            Le titre doit contenir au moins {{ MIN_TITLE_LENGTH }} caractères
          </small>
        </div>

        <div class="flex flex-col gap-2">
          <label class="font-medium">Votre message</label>
          <MessageEditor ref="editorRef" @content-update="handleContentUpdate" />
          <small v-if="contentText.length > 0 && contentText.length < MIN_MESSAGE_LENGTH" class="text-red-500">
            Le message doit contenir au moins {{ MIN_MESSAGE_LENGTH }} caractères
          </small>
        </div>
      </div>
    </template>

    <template v-else>
      <div class="text-center py-8">
        <i class="pi pi-check-circle text-6xl text-green-500 mb-4" />
        <p class="text-lg">Le sujet a été créé !</p>
      </div>
    </template>

    <template #footer>
      <div class="flex justify-end gap-2">
        <Button
          v-if="!isSent"
          label="Annuler"
          severity="secondary"
          @click="visibleModel = false"
        />
        <Button
          v-if="isSent"
          label="Fermer"
          severity="secondary"
          @click="visibleModel = false"
        />
        <Button
          v-if="!isSent"
          label="Créer"
          icon="pi pi-send"
          :disabled="!canSubmit"
          :loading="isSending"
          @click="handleSubmit"
        />
        <Button
          v-if="isSent && createdTopic"
          label="Aller sur le sujet"
          icon="pi pi-eye"
          @click="handleGoToTopic"
        />
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import { useToast } from 'primevue/usetoast'
import { computed, ref, watch } from 'vue'
import { useForumStore } from '../../store/forum/forum.js'
import MessageEditor from './MessageEditor.vue'

const MIN_TITLE_LENGTH = 5
const MIN_MESSAGE_LENGTH = 10

const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  forumSlug: {
    type: String,
    required: true
  }
})

const emit = defineEmits(['update:visible', 'created'])

const forumStore = useForumStore()
const toast = useToast()

const title = ref('')
const contentHtml = ref('')
const contentText = ref('')
const isSending = ref(false)
const isSent = ref(false)
const createdTopic = ref(null)
const editorRef = ref(null)

const visibleModel = computed({
  get: () => props.visible,
  set: (value) => emit('update:visible', value)
})

const canSubmit = computed(() => {
  return (
    title.value.trim().length >= MIN_TITLE_LENGTH &&
    contentText.value.trim().length >= MIN_MESSAGE_LENGTH
  )
})

function handleContentUpdate({ html, text }) {
  contentHtml.value = html
  contentText.value = text
}

async function handleSubmit() {
  if (!canSubmit.value) return

  isSending.value = true
  try {
    const topic = await forumStore.createTopic({
      forumSlug: props.forumSlug,
      title: title.value,
      message: contentHtml.value
    })
    createdTopic.value = topic
    isSent.value = true
    emit('created', topic)
  } catch (error) {
    console.error('Failed to create topic:', error)
    toast.add({
      severity: 'error',
      summary: 'Erreur',
      detail: 'Une erreur est survenue lors de la création du sujet',
      life: 5000
    })
  } finally {
    isSending.value = false
  }
}

function handleGoToTopic() {
  visibleModel.value = false
}

// Reset form when modal is closed
watch(visibleModel, (newValue) => {
  if (!newValue) {
    title.value = ''
    contentHtml.value = ''
    contentText.value = ''
    isSent.value = false
    createdTopic.value = null
    editorRef.value?.reset()
  }
})
</script>
