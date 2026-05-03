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
            class="ml-auto flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity"
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
          <Textarea
            v-model="editContent"
            rows="3"
            autoResize
            class="w-full text-sm"
          />
          <div class="flex justify-end gap-2 mt-1">
            <Button label="Annuler" size="small" severity="secondary" text @click="cancelEdit" />
            <Button
              label="Enregistrer"
              size="small"
              icon="pi pi-check"
              :disabled="!hasEditChanges"
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
import Textarea from 'primevue/textarea'
import { useConfirm } from 'primevue/useconfirm'
import { computed, ref } from 'vue'
import { useMentionParser } from '../../../composables/useMentionParser.js'
import { useUserSecurityStore } from '../../../store/user/security.js'
import Avatar from '../../User/Avatar.vue'

const props = defineProps({
  comments: { type: Array, default: () => [] },
  members: { type: Array, default: () => [] }
})

const emit = defineEmits(['edit', 'delete'])

const { parseToParts } = useMentionParser()
const userSecurityStore = useUserSecurityStore()
const confirm = useConfirm()

const editingId = ref(null)
const editContent = ref('')

const currentUserId = computed(() => userSecurityStore.userProfile?.id ?? null)

const currentMember = computed(() => {
  if (!currentUserId.value) return null
  return props.members.find((m) => m.user_id === currentUserId.value) ?? null
})

const isBandSpaceAdmin = computed(() => currentMember.value?.role === 'admin')

function canEdit(comment) {
  return currentUserId.value !== null && comment.author_id === currentUserId.value
}

function canDelete(comment) {
  return canEdit(comment) || isBandSpaceAdmin.value
}

const hasEditChanges = computed(() => {
  if (!editingId.value) return false
  const trimmed = editContent.value.trim()
  if (trimmed === '') return false
  const original = props.comments.find((c) => c.id === editingId.value)?.content ?? ''
  return trimmed !== original
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
}

function cancelEdit() {
  editingId.value = null
  editContent.value = ''
}

function saveEdit(comment) {
  emit('edit', comment.id, editContent.value.trim())
  editingId.value = null
  editContent.value = ''
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
