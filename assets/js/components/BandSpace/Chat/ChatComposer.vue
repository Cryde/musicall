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

    <Message v-if="recorder.error.value" severity="warn" :closable="false" class="mb-2">
      {{ recorder.error.value }}
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

    <!-- While recording, the row is the recording: nothing else can be added to a voice note (#974). -->
    <div v-if="recorder.isRecording.value" class="flex gap-2 items-center" role="status">
      <Button
        icon="pi pi-trash"
        severity="secondary"
        outlined
        aria-label="Annuler la note vocale"
        @click="recorder.cancel()"
      />
      <div class="flex flex-1 items-center gap-2 rounded-md border border-surface-200 dark:border-surface-700 px-3 py-2 text-sm">
        <span class="h-2.5 w-2.5 shrink-0 animate-pulse rounded-full bg-red-600" aria-hidden="true" />
        <span>Enregistrement</span>
        <span class="tabular-nums text-surface-600 dark:text-surface-300">
          {{ formatVoiceNoteDuration(recorder.elapsedSeconds.value) }} /
          {{ formatVoiceNoteDuration(MAX_VOICE_NOTE_SECONDS) }}
        </span>
      </div>
      <Button
        icon="pi pi-send"
        aria-label="Envoyer la note vocale"
        :loading="isSending"
        :disabled="isSending"
        @click="stopAndSendVoiceNote"
      />
    </div>

    <div v-else class="flex gap-2 items-end">
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
      <!-- Offered on an empty draft only, as on every messaging app: a voice note goes alone. -->
      <Button
        v-if="canRecord && !canSend && !isResolvingLink"
        icon="pi pi-microphone"
        aria-label="Enregistrer une note vocale"
        :disabled="isSending"
        @click="recorder.start()"
      />
      <Button
        v-else
        icon="pi pi-send"
        aria-label="Envoyer le message"
        :loading="isSending || isResolvingLink"
        :disabled="!canSend || isSending || isResolvingLink"
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
import { useRouter } from 'vue-router'
import bandSpaceChatApi from '../../../api/bandSpace/band-space-chat.js'
import { useVoiceRecorder } from '../../../composables/useVoiceRecorder.js'
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
import { attachmentIdentifierForUrl } from '../../../utils/chatPastedUrl.js'
import {
  formatVoiceNoteDuration,
  MAX_VOICE_NOTE_SECONDS,
  recordingExtension
} from '../../../utils/chatVoiceNote.js'
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

const router = useRouter()

/**
 * Pasted links still being resolved (#972). Sending, or starting a voice note, waits for them: the URL
 * has already been taken out of the text, so a message sent meanwhile would lose it, and the chip
 * would then land in the next draft instead.
 */
const pendingLinks = ref(0)
const isResolvingLink = computed(() => pendingLinks.value > 0)

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

const recorder = useVoiceRecorder({ onLimitReached: sendVoiceNote })
const canRecord = computed(() => canAttach.value && recorder.isSupported.value)
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

/**
 * Text pastes as it always did. Two pastes are taken over: an image (#973), and a link to an object
 * of this Band Space (#972), which becomes an attachment instead of a raw URL in the text.
 */
function handlePaste(event) {
  if (!canAttach.value) {
    return
  }

  const file = firstImageAmong(event.clipboardData?.files ?? [])
  if (file) {
    event.preventDefault()
    event.stopPropagation()
    setImage(file)
    return
  }

  const pasted = event.clipboardData?.getData('text/plain') ?? ''
  const identifier = attachmentIdentifierForUrl(pasted, {
    origin: window.location.origin,
    bandSpaceId: props.bandSpaceId,
    resolve: (path) => router.resolve(path)
  })
  if (identifier) {
    event.preventDefault()
    event.stopPropagation()
    attachPastedLink(identifier, pasted.trim())
  }
}

/**
 * The server decides, since the link may name something this member cannot point at. When it says no,
 * the link goes back into the text where it was pasted, or at the end if the caret has moved away.
 */
async function attachPastedLink(identifier, url) {
  pendingLinks.value++
  try {
    addAttachment(await bandSpaceChatApi.getAttachmentPreview(props.bandSpaceId, identifier))
  } catch {
    if (!composer.value?.insertText(url)) {
      content.value = content.value === '' ? url : `${content.value} ${url}`
    }
  } finally {
    pendingLinks.value--
  }
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
  if (!canSend.value || props.isSending || isResolvingLink.value) {
    return
  }

  const wasSent = await deliver(
    content.value,
    pickedIds.value,
    image.value ? { image: image.value.file } : null
  )

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

async function stopAndSendVoiceNote() {
  const recording = await recorder.stop()
  if (recording) {
    await sendVoiceNote(recording)
  }
}

/** A failure loses the recording, which a retry could not resend anyway once the recorder is gone. */
async function sendVoiceNote(recording) {
  const voiceNote = new File([recording], `note-vocale.${recordingExtension(recording.type)}`, {
    type: recording.type
  })

  if (await deliver('', [], { voiceNote })) {
    emit('sent')
  }
}

/** @returns {Promise<boolean>} whether the message went out */
async function deliver(message, attachmentIds, media) {
  sendError.value = ''
  attachmentError.value = ''

  try {
    await props.sendMessage(message, attachmentIds, media)
    return true
  } catch (e) {
    sendError.value = errorMessageFor(e)
    return false
  }
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
