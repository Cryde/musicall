<template>
  <div v-if="comments.length > 0" class="flex flex-col gap-3">
    <div
      v-for="comment in comments"
      :key="comment.id"
      class="group flex gap-3"
    >
      <div class="flex-shrink-0">
        <Avatar
          :username="comment.author_username"
          :picture-url="comment.author_profile_picture_url"
          size="md"
        />
      </div>
      <div class="flex-1 min-w-0">
        <div class="flex items-center gap-2">
          <span class="text-sm font-medium text-surface-800 dark:text-surface-100">
            {{ comment.author_username }}
          </span>
          <span class="text-xs text-surface-400">
            {{ formatRelative(comment.creation_datetime) }}
          </span>
          <span v-if="comment.update_datetime" class="text-xs text-surface-400 italic">
            (modifié)
          </span>

          <div
            v-if="canEdit(comment) || canDelete(comment)"
            class="ml-auto flex items-center gap-1 lg:opacity-0 lg:group-hover:opacity-100 transition-opacity"
          >
            <button
              v-if="canEdit(comment)"
              type="button"
              class="text-surface-400 hover:text-primary"
              title="Modifier"
              @click="startEdit(comment)"
            >
              <i class="pi pi-pencil text-xs" />
            </button>
            <button
              v-if="canDelete(comment)"
              type="button"
              class="text-surface-400 hover:text-red-500"
              title="Supprimer"
              @click="confirmDelete(comment)"
            >
              <i class="pi pi-trash text-xs" />
            </button>
          </div>
        </div>

        <div v-if="editingId === comment.id" class="mt-1">
          <MentionEditor
            ref="editor"
            v-model="editContent"
            :members="members"
            aria-label="Modifier le commentaire"
            editor-class="min-h-[4.5rem] max-h-60 rounded-lg p-3"
            submit-on="ctrl-enter"
            :disabled="isSaving"
            @submit="saveEdit(comment)"
          />
          <Message v-if="saveError" severity="error" :closable="false" class="mt-1">
            {{ saveError }}
          </Message>
          <div class="flex justify-end gap-2 mt-1">
            <Button
              label="Annuler"
              size="small"
              severity="secondary"
              text
              :disabled="isSaving"
              @click="cancelEdit"
            />
            <Button
              label="Enregistrer"
              size="small"
              icon="pi pi-check"
              :loading="isSaving"
              :disabled="!hasEditChanges || isSaving"
              @click="saveEdit(comment)"
            />
          </div>
        </div>
        <p v-else class="text-sm text-surface-600 dark:text-surface-300 mt-0.5 whitespace-pre-wrap">
          <template v-for="(part, index) in parts(comment.content)" :key="index">
            <span v-if="part.type === 'mention'" class="text-primary font-semibold">@{{ part.username }}</span>
            <template v-else>{{ part.value }}</template>
          </template>
        </p>
      </div>
    </div>
  </div>
  <p v-else class="text-sm text-surface-400 italic">Aucun commentaire</p>
</template>

<script setup>
import { formatDistanceToNow } from 'date-fns'
import { fr } from 'date-fns/locale'
import Button from 'primevue/button'
import Message from 'primevue/message'
import { useConfirm } from 'primevue/useconfirm'
import { computed, nextTick, ref } from 'vue'
import { useMentionParser } from '../../../composables/useMentionParser.js'
import { useUserSecurityStore } from '../../../store/user/security.js'
import MentionEditor from '../../Global/MentionEditor.vue'
import Avatar from '../../User/Avatar.vue'

const props = defineProps({
  comments: { type: Array, default: () => [] },
  members: { type: Array, default: () => [] },
  // The thread of an archived task is history: it is still read, never written.
  readOnly: { type: Boolean, default: false },
  /**
   * Awaitable, like the write box above it, so the row stays open until the save lands. It used to
   * close on the click, which cost you the edit whenever the request failed; with mentions in the box
   * that means re-picking every member you named.
   */
  saveComment: { type: Function, default: null }
})

const emit = defineEmits(['delete'])

const { parseToParts } = useMentionParser()
const userSecurityStore = useUserSecurityStore()
const confirm = useConfirm()

const editor = ref(null)
const editingId = ref(null)
const editContent = ref('')
const isSaving = ref(false)
const saveError = ref('')

const currentUserId = computed(() => userSecurityStore.userProfile?.id ?? null)

const currentMember = computed(() => {
  if (!currentUserId.value) return null
  return props.members.find((m) => m.user_id === currentUserId.value) ?? null
})

const isBandSpaceAdmin = computed(() => currentMember.value?.role === 'admin')

function canEdit(comment) {
  if (props.readOnly) return false
  return currentUserId.value !== null && comment.author_id === currentUserId.value
}

function canDelete(comment) {
  if (props.readOnly) return false
  return canEdit(comment) || isBandSpaceAdmin.value
}

/**
 * Both sides trimmed, not just the typed one. A contenteditable settles on whatever trailing
 * whitespace the browser wants, so comparing a trimmed box against an untrimmed stored string reports
 * "unsaved changes" on a comment nobody touched, and saving it would bump the date and paint
 * "(modifié)" for nothing.
 */
const hasEditChanges = computed(() => {
  if (!editingId.value) return false
  const trimmed = editContent.value.trim()
  if (trimmed === '') return false
  const original = props.comments.find((c) => c.id === editingId.value)?.content ?? ''
  return trimmed !== original.trim()
})

function parts(content) {
  return parseToParts(content, props.members)
}

function formatRelative(dateStr) {
  return formatDistanceToNow(new Date(dateStr), { addSuffix: true, locale: fr })
}

function startEdit(comment) {
  editingId.value = comment.id
  editContent.value = comment.content
  saveError.value = ''
  // At the end, because you reopen a comment to add to it rather than to overwrite it.
  nextTick(() => editorRef()?.focusAtEnd())
}

/** The editor sits behind a `v-if` in a `v-for`, so Vue hands the ref back as a single element list. */
function editorRef() {
  return Array.isArray(editor.value) ? editor.value[0] : editor.value
}

function cancelEdit() {
  editingId.value = null
  editContent.value = ''
  saveError.value = ''
}

async function saveEdit(comment) {
  if (!hasEditChanges.value || isSaving.value) {
    return
  }

  isSaving.value = true
  saveError.value = ''

  try {
    await props.saveComment(comment.id, editContent.value.trim())
    cancelEdit()
  } catch (e) {
    console.error('Failed to save the comment:', e)
    saveError.value = e.message || "Le commentaire n'a pas pu être modifié."
  } finally {
    isSaving.value = false
  }
}

function confirmDelete(comment) {
  confirm.require({
    message: 'Supprimer ce commentaire ?',
    header: 'Confirmer la suppression',
    icon: 'pi pi-exclamation-triangle',
    rejectLabel: 'Annuler',
    acceptLabel: 'Supprimer',
    acceptClass: 'p-button-danger',
    accept: () => emit('delete', comment.id)
  })
}
</script>
