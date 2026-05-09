<template>
  <Dialog
    v-model:visible="visible"
    modal
    :header="dialogHeader"
    :style="{ width: '28rem' }"
    @hide="resetState"
  >
    <div class="flex flex-col gap-4">
      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Nom</label>
        <InputText
          v-model="form.name"
          placeholder="Ex : Live, Setlists, 2025"
          size="small"
          class="w-full"
          :disabled="isSubmitting"
          @keydown.enter.prevent="handleSubmit"
        />
        <small v-if="fieldErrors.name" class="text-red-500">{{ fieldErrors.name }}</small>
      </div>

      <div v-if="mode !== 'create-sub'" class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Dossier parent</label>
        <Select
          v-model="form.parentId"
          :options="parentOptions"
          option-label="label"
          option-value="value"
          placeholder="Racine"
          :show-clear="true"
          :disabled="isSubmitting"
        />
      </div>

      <Message v-if="globalError" severity="error" :closable="false">{{ globalError }}</Message>
    </div>

    <template #footer>
      <Button
        label="Annuler"
        severity="secondary"
        text
        :disabled="isSubmitting"
        @click="visible = false"
      />
      <Button
        :label="submitLabel"
        :loading="isSubmitting"
        :disabled="!form.name.trim() || isSubmitting"
        @click="handleSubmit"
      />
    </template>
  </Dialog>
</template>

<script setup>
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Select from 'primevue/select'
import { computed, reactive, ref, watch } from 'vue'
import { useBandFilesStore } from '../../../store/bandSpace/bandSpaceFiles.js'

const props = defineProps({
  bandSpaceId: { type: String, required: true },
  // 'create-root' | 'create-sub' | 'edit'
  mode: { type: String, required: true },
  folder: { type: Object, default: null },
  parentId: { type: String, default: null }
})

const emit = defineEmits(['saved'])

const visible = defineModel('visible', { type: Boolean, default: false })

const filesStore = useBandFilesStore()

const form = reactive({ name: '', parentId: null })
const isSubmitting = ref(false)
const fieldErrors = reactive({ name: null })
const globalError = ref(null)

const dialogHeader = computed(() => {
  if (props.mode === 'create-root') return 'Nouveau dossier'
  if (props.mode === 'create-sub') return 'Nouveau sous-dossier'
  return 'Renommer / déplacer le dossier'
})

const submitLabel = computed(() => (props.mode === 'edit' ? 'Enregistrer' : 'Créer'))

const parentOptions = computed(() => {
  const out = []
  // Exclude self and descendants when editing.
  const excludedIds = new Set()
  if (props.mode === 'edit' && props.folder?.id) {
    excludedIds.add(props.folder.id)
    collectDescendants(filesStore.folders, props.folder.id, excludedIds)
  }

  const walk = (nodes, depth) => {
    for (const node of nodes) {
      if (excludedIds.has(node.id)) continue
      out.push({ label: '— '.repeat(depth) + node.name, value: node.id })
      if (Array.isArray(node.children) && node.children.length > 0) {
        walk(node.children, depth + 1)
      }
    }
  }
  walk(filesStore.folders, 0)
  return out
})

function collectDescendants(nodes, targetId, set, found = false) {
  for (const node of nodes) {
    const isTarget = node.id === targetId
    if (isTarget || found) {
      set.add(node.id)
      if (Array.isArray(node.children)) {
        collectDescendants(node.children, targetId, set, true)
      }
    } else if (Array.isArray(node.children)) {
      collectDescendants(node.children, targetId, set, false)
    }
  }
}

watch(
  visible,
  (open) => {
    if (open) initFromProps()
  },
  { immediate: true }
)

function initFromProps() {
  fieldErrors.name = null
  globalError.value = null
  if (props.mode === 'edit' && props.folder) {
    form.name = props.folder.name
    form.parentId = props.folder.parent_id
  } else if (props.mode === 'create-sub') {
    form.name = ''
    form.parentId = props.parentId
  } else {
    form.name = ''
    form.parentId = null
  }
}

async function handleSubmit() {
  const name = form.name.trim()
  if (!name) {
    fieldErrors.name = 'Le nom est requis'
    return
  }
  fieldErrors.name = null
  globalError.value = null
  isSubmitting.value = true

  try {
    if (props.mode === 'edit') {
      const payload = {}
      if (name !== props.folder.name) payload.name = name
      if (form.parentId !== props.folder.parent_id) payload.parent_id = form.parentId
      if (Object.keys(payload).length === 0) {
        visible.value = false
        return
      }
      await filesStore.updateFolder(props.bandSpaceId, props.folder.id, payload)
    } else {
      await filesStore.createFolder(props.bandSpaceId, {
        name,
        parentId: form.parentId
      })
    }
    emit('saved')
    visible.value = false
  } catch (e) {
    if (e.isValidationError) {
      const nameViolation = e.violationsByField?.name?.[0]?.message
      if (nameViolation) {
        fieldErrors.name = nameViolation
      } else {
        globalError.value = e.message
      }
    } else {
      globalError.value = e.message
    }
  } finally {
    isSubmitting.value = false
  }
}

function resetState() {
  form.name = ''
  form.parentId = null
  fieldErrors.name = null
  globalError.value = null
  isSubmitting.value = false
}
</script>
