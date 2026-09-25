<template>
  <!-- Paste and drop are caught on the whole composer, before the editor sees them, so an image
       never lands in the text as markup (#973). -->
  <div
    class="border-t border-surface-200 dark:border-surface-700 p-4"
    @paste.capture="handlePaste"
    @dragover.prevent
    @drop.prevent="handleDrop"
  >
    <Message v-if="sendError" severity="error" :closable="false" class="mb-2">
      {{ sendError }}
    </Message>

    <Message v-if="attachmentError" severity="warn" :closable="false" class="mb-2">
      {{ attachmentError }}
    </Message>

    <div v-if="image" class="relative mb-2 inline-block">
      <img
        :src="image.previewUrl"
        alt="Image à envoyer"
        class="h-20 w-20 rounded-md object-cover border border-surface-200 dark:border-surface-700"
      />
      <Button
        icon="pi pi-times"
        rounded
        size="small"
        severity="secondary"
        class="absolute! -right-2 -top-2 h-6! w-6!"
        aria-label="Retirer l'image"
        :disabled="isSending"
        @click="removeImage"
      />
    </div>

    <ChatComposerAttachments
      v-if="attachments.length > 0"
      :attachments="attachments"
      :disabled="isSending"
      @remove="removeAttachment"
    />

    <div class="flex gap-2 items-end">
      <Button
        v-if="canAttach"
        icon="pi pi-paperclip"
        severity="secondary"
        outlined
        aria-label="Joindre un élément du Band Space"
        :disabled="isSending"
        @click="isPickerVisible = true"
      />
      <!-- A button as well as paste and drop: on a phone, the picker is the only way to a photo. -->
      <Button
        v-if="canAttach"
        icon="pi pi-image"
        severity="secondary"
        outlined
        aria-label="Ajouter une image"
        :disabled="isSending"
        @click="imageInput?.click()"
      />
      <input
        v-if="canAttach"
        ref="imageInput"
        type="file"
        class="hidden"
        :accept="ACCEPTED_IMAGE_TYPES.join(',')"
        @change="handleImagePicked"
      />
      <MentionEditor
        ref="composer"
        v-model="content"
        class="flex-1"
        :members="suggestionSource"
        aria-label="Votre message"
        placeholder="Votre message..."
        editor-class="min-h-[3.5rem] max-h-40 rounded-md px-3 py-2"
        submit-on="enter"
        :disabled="isSending"
        @submit="send"
      />
      <Button
        icon="pi pi-send"
        aria-label="Envoyer le message"
        :loading="isSending"
        :disabled="!canSend || isSending"
        @click="send"
      />
    </div>

    <ChatAttachmentPicker
      v-if="canAttach"
      v-model:visible="isPickerVisible"
      :band-space-id="bandSpaceId"
      :picked-ids="pickedIds"
      @pick="addAttachment"
    />
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import Message from 'primevue/message'
import { computed, nextTick, onBeforeUnmount, ref } from 'vue'
import { EVERYONE_MEMBER } from '../../../constants/chatMention.js'
import {
  addToDraft,
  canSendDraft,
  draftAfterSend,
  draftIdentifiers,
  removeFromDraft
} from '../../../utils/chatAttachmentDraft.js'
import {
  ACCEPTED_IMAGE_TYPES,
  firstImageAmong,
  imageRefusalFor
} from '../../../utils/chatImageDraft.js'
import MentionEditor from '../../Global/MentionEditor.vue'
import ChatAttachmentPicker from './ChatAttachmentPicker.vue'
import ChatComposerAttachments from './ChatComposerAttachments.vue'

const props = defineProps({
  members: { type: Array, default: () => [] },
  /**
   * Where the message goes, supplied by the parent rather than reached for here (#994): the band
   * space tab sends through its own store, the inbox through the one holding the open conversation.
   * Both end at the same endpoint, and the editor below does not need to know which.
   */
  sendMessage: { type: Function, required: true },
  isSending: { type: Boolean, default: false },
  /**
   * Which space the picker searches, and by its presence whether there is a picker at all (#971).
   * The inbox renders a channel through this same composer and even reaches the same endpoint, but
   * `Thread.vue` draws no attachment card, so a member picking one there would see nothing come of
   * it. It leaves this out until that half exists.
   */
  bandSpaceId: { type: String, default: null }
})

const emit = defineEmits(['sent'])

const composer = ref(null)
/** What gets sent: the composer's content in the stored `@[uuid]` format, never what is on screen. */
const content = ref('')
const sendError = ref('')

/** The Band Space objects this message will point at, see chatAttachmentDraft. */
const attachments = ref([])
/** The image this message will carry (#973), with a local preview URL to revoke once it is gone. */
const image = ref(null)
const imageInput = ref(null)
const canSend = computed(
  () => canSendDraft(content.value, attachments.value) || image.value !== null
)
const attachmentError = ref('')
const isPickerVisible = ref(false)

const canAttach = computed(() => !!props.bandSpaceId)
const pickedIds = computed(() => draftIdentifiers(attachments.value))

const suggestionSource = computed(() => [EVERYONE_MEMBER, ...props.members])

/**
 * The single way into the draft, whatever found the object. The picker refuses a duplicate and a
 * full list on its own, so the message below is what the next producer gets for free (#972).
 */
function addAttachment(result) {
  const { attachments: next, error } = addToDraft(attachments.value, result)
  attachments.value = next
  attachmentError.value = error ?? ''
}

function removeAttachment(id) {
  attachments.value = removeFromDraft(attachments.value, id)
  attachmentError.value = ''
}

function setImage(file) {
  const refusal = imageRefusalFor(file)
  if (refusal) {
    attachmentError.value = refusal
    return
  }

  removeImage()
  image.value = { file, previewUrl: URL.createObjectURL(file) }
}

function removeImage() {
  if (image.value) {
    URL.revokeObjectURL(image.value.previewUrl)
  }
  image.value = null
  attachmentError.value = ''
}

/** Text pastes as it always did; only a clipboard holding an image is taken over. */
function handlePaste(event) {
  const file = canAttach.value ? firstImageAmong(event.clipboardData?.files ?? []) : null
  if (!file) {
    return
  }

  event.preventDefault()
  event.stopPropagation()
  setImage(file)
}

function handleDrop(event) {
  const file = canAttach.value ? firstImageAmong(event.dataTransfer?.files ?? []) : null
  if (file) {
    setImage(file)
  }
}

function handleImagePicked(event) {
  const [file] = event.target.files ?? []
  if (file) {
    setImage(file)
  }
  // Cleared so picking the same photo again after removing it still fires a change.
  event.target.value = ''
}

onBeforeUnmount(removeImage)

async function send() {
  const message = content.value
  if (!canSend.value || props.isSending) {
    return
  }

  sendError.value = ''
  attachmentError.value = ''
  let wasSent = false

  try {
    await props.sendMessage(message, pickedIds.value, image.value?.file ?? null)
    wasSent = true
  } catch (e) {
    sendError.value = errorMessageFor(e)
  }

  // The text, the references and the image leave together and stay together: a failure keeps them
  // all, so the retry is one click rather than a search for each object again.
  attachments.value = draftAfterSend(attachments.value, wasSent)
  if (!wasSent) {
    return
  }

  content.value = ''
  removeImage()
  emit('sent')
  nextTick(() => composer.value?.focus())
}

/**
 * handleApiError already carries the server's own French sentence, so a message too long or a space
 * in its deletion grace period explains itself. Only the rate limiter needs wording of our own, since
 * its 429 body says nothing a member could act on.
 */
function errorMessageFor(error) {
  if (error.status === 429) {
    return 'Trop de messages envoyés. Veuillez patienter un instant.'
  }

  console.error('Failed to send the message:', error)

  return error.message || "Le message n'a pas pu être envoyé."
}
</script>
