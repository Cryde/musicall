<template>
  <Dialog
    v-model:visible="visible"
    modal
    header="Importer des fichiers"
    :style="{ width: '36rem' }"
    :close-on-escape="!isUploading"
    @hide="handleHide"
  >
    <div class="flex flex-col gap-4">
      <div
        class="border-2 border-dashed rounded-lg p-6 text-center cursor-pointer transition-colors"
        :class="dropZoneClasses"
        @click="triggerFilePicker"
        @dragover.prevent="isDragging = true"
        @dragleave.prevent="isDragging = false"
        @drop.prevent="handleDrop"
      >
        <input
          ref="fileInput"
          type="file"
          class="hidden"
          multiple
          @change="handleFileChange"
        />

        <i class="pi pi-cloud-upload text-3xl text-surface-400 mb-2" aria-hidden="true"></i>
        <p class="text-sm text-surface-600 dark:text-surface-300">
          Glissez-déposez un ou plusieurs fichiers ou
          <span class="text-primary-500 underline">cliquez pour parcourir</span>
        </p>
      </div>

      <ul
        v-if="queue.length > 0"
        class="flex flex-col gap-2 max-h-64 overflow-y-auto"
        aria-label="Fichiers à importer"
      >
        <li
          v-for="item in queue"
          :key="item.id"
          class="flex items-start gap-2 rounded-md border border-surface-200 dark:border-surface-700 px-3 py-2"
        >
          <i
            :class="statusIconClass(item.status)"
            role="img"
            :aria-label="statusLabel(item.status)"
          ></i>

          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium truncate" :title="item.name">{{ item.name }}</p>
            <p class="text-xs text-surface-400">{{ formatBytes(item.size) }}</p>

            <ProgressBar
              v-if="item.status === UPLOAD_STATUS.Uploading"
              :value="item.progress"
              :show-value="false"
              :aria-label="`Progression de ${item.name}`"
              class="mt-1"
              style="height: 4px"
            />
            <p v-if="item.error" class="text-xs text-red-500 mt-1">{{ item.error }}</p>
          </div>

          <Button
            v-if="!isUploading"
            icon="pi pi-times"
            :aria-label="`Retirer ${item.name}`"
            text
            rounded
            size="small"
            @click.stop="handleRemoveFile(item.id)"
          />
        </li>
      </ul>

      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Dossier</label>
        <Select
          v-model="form.folderId"
          aria-label="Dossier"
          :options="folderOptions"
          option-label="label"
          option-value="value"
          placeholder="Racine"
          :show-clear="true"
          :disabled="isUploading || activeFolderIsVirtual"
        />
        <small v-if="activeFolderIsVirtual" class="text-surface-400 italic">
          Les dossiers virtuels sont remplis automatiquement par les attachements.
        </small>
      </div>

      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Tags</label>
        <MultiSelect
          v-model="form.tagIds"
          aria-label="Tags"
          :options="tags"
          option-label="name"
          option-value="id"
          placeholder="Sélectionner des tags"
          :max-selected-labels="3"
          :disabled="isUploading"
        />

        <div class="flex items-center gap-2 mt-1">
          <InputText
            v-model="newTagName"
            placeholder="Créer un nouveau tag"
            size="small"
            class="flex-1"
            :disabled="isUploading || isCreatingTag"
            @keydown.enter.prevent="handleCreateTag"
          />
          <Button
            icon="pi pi-plus"
            aria-label="Créer le tag"
            size="small"
            severity="secondary"
            :disabled="isUploading || isCreatingTag || !newTagName.trim()"
            :loading="isCreatingTag"
            @click="handleCreateTag"
          />
        </div>
        <small v-if="createTagError" class="text-red-500">{{ createTagError }}</small>
      </div>

      <div v-if="isUploading" class="flex flex-col gap-1">
        <ProgressBar :value="summary.percent" />
        <!-- Only the file being sent is announced: the percentage changes on every chunk and would
             turn the live region into a stream nobody can listen to. -->
        <p class="text-xs text-surface-500 text-center">
          <span aria-live="polite">
            Import… fichier {{ summary.position }} sur {{ summary.total }}
          </span>
          <span aria-hidden="true">({{ summary.percent }} %)</span>
        </p>
      </div>

      <Message v-if="pauseNotice" severity="warn" :closable="false">{{ pauseNotice }}</Message>

      <Message v-if="batchNotice" severity="warn" :closable="false">{{ batchNotice }}</Message>

      <Message
        v-if="summary.isFinished && !isUploading"
        :severity="summary.hasFailures ? 'warn' : 'success'"
        :closable="false"
      >
        {{ summary.label }}
      </Message>
    </div>

    <template #footer>
      <Button
        v-if="isUploading"
        label="Arrêter"
        icon="pi pi-stop"
        severity="secondary"
        @click="cancelBatch"
      />
      <template v-else>
        <Button label="Fermer" severity="secondary" text @click="visible = false" />
        <Button
          v-if="hasUnsent"
          label="Réessayer les fichiers non envoyés"
          icon="pi pi-refresh"
          severity="secondary"
          @click="handleRetry"
        />
        <Button
          label="Importer"
          icon="pi pi-cloud-upload"
          :disabled="!hasQueuedFiles"
          @click="handleUpload"
        />
      </template>
    </template>
  </Dialog>
</template>

<script setup>
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import MultiSelect from 'primevue/multiselect'
import ProgressBar from 'primevue/progressbar'
import Select from 'primevue/select'
import { computed, reactive, ref, watch } from 'vue'
import { useBandFilesStore } from '../../../store/bandSpace/bandSpaceFiles.js'
import {
  appendToQueue,
  applyUploadFailure,
  cancelPendingUploads,
  hasUnsentItems,
  hasUploadInFlight,
  msUntilNextUploadSlot,
  nextQueuedItem,
  rateLimitPauseNotice,
  recordUploadStart,
  removeFromQueue,
  requeueUnsentItems,
  sleepUnlessAborted,
  summarizeQueue,
  UPLOAD_STATUS,
  withQueueItem
} from '../../../utils/fileUploadQueue.js'
import { formatBytes } from '../../../utils/formatBytes.js'

const props = defineProps({
  bandSpaceId: { type: String, required: true }
})

const emit = defineEmits(['saved'])

const visible = defineModel('visible', { type: Boolean, default: false })

const filesStore = useBandFilesStore()

const fileInput = ref(null)
const isDragging = ref(false)

/** The files to send, in the order they were picked. See utils/fileUploadQueue.js. */
const queue = ref([])

const form = reactive({
  folderId: null,
  tagIds: []
})

const newTagName = ref('')
const isCreatingTag = ref(false)
const createTagError = ref(null)

const isUploading = ref(false)
/** Why the batch is waiting rather than sending, and roughly for how long. */
const pauseNotice = ref(null)
/** Why the batch stopped short, when it did. */
const batchNotice = ref(null)

/**
 * When each of the recent uploads started, so the batch paces itself under the server's limiter
 * instead of discovering it at file thirty one. Deliberately not cleared with the form: the
 * server's window outlives the dialog being closed and reopened.
 */
let sentAt = []

/**
 * Stops a batch the member walked away from writing into the state of the one after it. Every
 * awaited step checks it back, because closing the dialog resets everything under the running loop.
 */
let runningBatch = 0
let abortController = null

/**
 * What the running batch has achieved so far. Kept out of runBatch's own scope because closing the
 * dialog has to be able to report it too, and that happens outside the loop.
 */
let tally = { uploadedCount: 0, quotaApproaching: false, interrupted: false }

const tags = computed(() => filesStore.tags)

const activeFolderId = computed(() => filesStore.activeFolderId)
const activeFolderIsVirtual = computed(
  () => typeof activeFolderId.value === 'string' && activeFolderId.value.startsWith('virtual:')
)

const folderOptions = computed(() => {
  const out = []
  const walk = (nodes, depth) => {
    for (const node of nodes) {
      out.push({ label: '— '.repeat(depth) + node.name, value: node.id })
      if (Array.isArray(node.children) && node.children.length > 0) {
        walk(node.children, depth + 1)
      }
    }
  }
  walk(filesStore.folders, 0)
  return out
})

const dropZoneClasses = computed(() => {
  if (isDragging.value) {
    return 'border-primary-500 bg-primary-50 dark:bg-primary-950'
  }
  return 'border-surface-300 dark:border-surface-700 hover:bg-surface-50 dark:hover:bg-surface-800'
})

const summary = computed(() => summarizeQueue(queue.value))
const hasQueuedFiles = computed(() => nextQueuedItem(queue.value) !== null)
const hasUnsent = computed(() => hasUnsentItems(queue.value))

watch(visible, (open) => {
  if (open) {
    if (!activeFolderIsVirtual.value && typeof activeFolderId.value === 'string') {
      form.folderId = activeFolderId.value
    } else {
      form.folderId = null
    }
  }
})

// Open while a batch runs too: the loop reads the queue back on every turn, so files dropped in
// mid import join the end of it rather than being swallowed.
function triggerFilePicker() {
  fileInput.value?.click()
}

function handleFileChange(event) {
  addFiles(event.target.files)
  // Cleared so picking the very same file again still fires a change event.
  if (fileInput.value) fileInput.value.value = ''
}

function handleDrop(event) {
  isDragging.value = false
  addFiles(event.dataTransfer?.files)
}

function addFiles(files) {
  if (!files || files.length === 0) return
  // The limit is passed in rather than read inside the queue module so the module stays pure and
  // testable. An oversize file lands in the list already failed and is never sent.
  queue.value = appendToQueue(queue.value, files, filesStore.maxUploadSizeBytes)
  batchNotice.value = null
}

function handleRemoveFile(id) {
  queue.value = removeFromQueue(queue.value, id)
}

async function handleCreateTag() {
  const name = newTagName.value.trim()
  if (!name) return
  isCreatingTag.value = true
  createTagError.value = null
  try {
    const created = await filesStore.createTag(props.bandSpaceId, { name })
    form.tagIds = [...form.tagIds, created.id]
    newTagName.value = ''
  } catch (e) {
    createTagError.value = e.message
  } finally {
    isCreatingTag.value = false
  }
}

function handleUpload() {
  if (!hasQueuedFiles.value) return
  batchNotice.value = null

  return runBatch()
}

function handleRetry() {
  queue.value = requeueUnsentItems(queue.value)
  batchNotice.value = null

  return runBatch()
}

/**
 * Sends the queue one file at a time.
 *
 * Every await is a place the member can close the dialog, which resets the queue under this loop,
 * so each one is followed by a batch check before anything is written back.
 */
async function runBatch() {
  const batch = ++runningBatch
  abortController = new AbortController()
  isUploading.value = true
  tally = { uploadedCount: 0, quotaApproaching: false, interrupted: false }

  try {
    let item = nextQueuedItem(queue.value)
    while (item) {
      const wait = msUntilNextUploadSlot(sentAt, Date.now())
      if (wait > 0) {
        pauseNotice.value = rateLimitPauseNotice(wait)
        const stopped = await sleepUnlessAborted(wait, abortController.signal)
        if (batch !== runningBatch) return
        if (stopped) {
          applyCancellation()
          break
        }
      }
      pauseNotice.value = null

      queue.value = withQueueItem(queue.value, item.id, {
        status: UPLOAD_STATUS.Uploading,
        progress: 0,
        error: null
      })
      sentAt = recordUploadStart(sentAt, Date.now())

      try {
        const result = await uploadOne(item, batch)
        if (batch !== runningBatch) return
        queue.value = withQueueItem(queue.value, item.id, {
          status: UPLOAD_STATUS.Uploaded,
          progress: 100
        })
        tally.uploadedCount += 1
        tally.quotaApproaching = tally.quotaApproaching || result.quotaApproaching
      } catch (e) {
        if (batch !== runningBatch) return
        const outcome = applyUploadFailure(queue.value, item.id, e)
        queue.value = outcome.queue
        // Only a cut off request can have been taken by the server without the list knowing. A
        // batch the quota or the limiter stopped left nothing behind, so it needs no reread.
        tally.interrupted = tally.interrupted || outcome.kind === 'cancelled'

        if (outcome.pauseMs > 0) {
          pauseNotice.value = outcome.notice
          const stopped = await sleepUnlessAborted(outcome.pauseMs, abortController.signal)
          if (batch !== runningBatch) return
          pauseNotice.value = null
          if (stopped) {
            applyCancellation()
            break
          }
        } else {
          batchNotice.value = outcome.notice
        }

        if (outcome.stop) break
      }

      item = nextQueuedItem(queue.value)
    }

    const summarized = summarizeQueue(queue.value)
    reportBatch(summarized)

    // Nothing left to say once everything went through: closing is the answer the single file
    // dialog always gave. A batch with anything unsent stays open, because that report is the point.
    if (summarized.total > 0 && summarized.unsent === 0) {
      visible.value = false
    }
  } finally {
    if (batch === runningBatch) {
      isUploading.value = false
      pauseNotice.value = null
      abortController = null
    }
  }
}

/** @returns {Promise<{file: Object, quotaApproaching: boolean}>} */
function uploadOne(item, batch) {
  return filesStore.uploadFile(
    props.bandSpaceId,
    { file: item.file, folderId: form.folderId, tagIds: form.tagIds },
    (percent) => {
      if (batch !== runningBatch) return
      queue.value = withQueueItem(queue.value, item.id, { progress: percent })
    },
    abortController?.signal
  )
}

/**
 * Drops the file in flight and everything behind it.
 *
 * One abort covers both states the batch can be stopped in, which is the whole reason the pauses
 * wait on this same signal: most of a rate limited run is spent between requests, so aborting only
 * the request would leave the button doing nothing at all for up to twenty seconds.
 *
 * A request cut off may already have been taken by the server, so the batch reports itself as
 * interrupted and Files.vue reads the list back. Anything else would leave a file on screen that is
 * not stored, or one stored that is not on screen.
 */
function cancelBatch() {
  abortController?.abort()
}

/** The queue side of being stopped: whatever was not finished with is given up on and said so. */
function applyCancellation() {
  const cancelled = cancelPendingUploads(queue.value)
  queue.value = cancelled.queue
  batchNotice.value = cancelled.notice
  pauseNotice.value = null
}

/**
 * Tells Files.vue what the batch did, once, so the toast and the reread happen whether the batch
 * ran to its end or the member walked out on it.
 *
 * `total` goes with the label because a batch of one still deserves the sentence the single file
 * dialog has always shown, rather than a bare count.
 *
 * @param {{label: string, total: number}} summarized
 */
function reportBatch(summarized) {
  if (tally.uploadedCount === 0 && !tally.interrupted) return

  emit('saved', { ...tally, label: summarized.label, total: summarized.total })
  tally = { uploadedCount: 0, quotaApproaching: false, interrupted: false }
}

function handleHide() {
  // Closing on a running batch abandons a request the server may already have taken, so the batch
  // still has to be reported: without this the file it stored is missing from a list that claims to
  // be complete. Closing during one of the pauses has nothing on the wire to abandon, so it costs
  // no reread. The summary is read before the queue is emptied.
  if (isUploading.value) {
    tally.interrupted = tally.interrupted || hasUploadInFlight(queue.value)
    cancelBatch()
    reportBatch(summarizeQueue(queue.value))
  }

  // Past this point the running loop belongs to nobody: the check on runningBatch stops it writing
  // into the state the next batch is about to be given.
  runningBatch += 1
  isUploading.value = false
  resetForm()
}

function resetForm() {
  queue.value = []
  form.folderId = null
  form.tagIds = []
  newTagName.value = ''
  isCreatingTag.value = false
  createTagError.value = null
  pauseNotice.value = null
  batchNotice.value = null
  abortController = null
  if (fileInput.value) fileInput.value.value = ''
}

const STATUS_ICONS = {
  [UPLOAD_STATUS.Queued]: 'pi pi-clock text-surface-400',
  [UPLOAD_STATUS.Uploading]: 'pi pi-spin pi-spinner text-primary-500',
  [UPLOAD_STATUS.Uploaded]: 'pi pi-check-circle text-green-600',
  [UPLOAD_STATUS.Failed]: 'pi pi-times-circle text-red-600',
  [UPLOAD_STATUS.Skipped]: 'pi pi-ban text-amber-600',
  [UPLOAD_STATUS.Cancelled]: 'pi pi-ban text-surface-400',
  [UPLOAD_STATUS.Rejected]: 'pi pi-ban text-red-600'
}

const STATUS_LABELS = {
  [UPLOAD_STATUS.Queued]: 'En attente',
  [UPLOAD_STATUS.Uploading]: 'Envoi en cours',
  [UPLOAD_STATUS.Uploaded]: 'Importé',
  [UPLOAD_STATUS.Failed]: 'Échec',
  [UPLOAD_STATUS.Skipped]: 'Non envoyé',
  [UPLOAD_STATUS.Cancelled]: 'Interrompu',
  [UPLOAD_STATUS.Rejected]: 'Refusé'
}

function statusIconClass(status) {
  return `${STATUS_ICONS[status]} mt-0.5`
}

function statusLabel(status) {
  return STATUS_LABELS[status]
}
</script>
