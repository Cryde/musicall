<template>
  <div class="flex items-center gap-2 min-w-0 flex-1">
    <template v-if="!editing">
      <component :is="tag" class="m-0 min-w-0 truncate font-semibold" :class="nameClass">{{ name }}</component>
      <Button
        icon="pi pi-pencil"
        :aria-label="t('band_space_files.file_list.menu.rename')"
        size="small"
        text
        rounded
        :disabled="disabled"
        @click="startEditing"
      />
    </template>
    <template v-else>
      <InputText
        ref="input"
        v-model="draft"
        size="small"
        class="flex-1 min-w-0"
        :aria-label="t('band_space_files.file_list.menu.rename')"
        :disabled="saving"
        @keydown.enter.prevent="commit"
        @keydown.escape.stop="editing = false"
      />
      <Button
        icon="pi pi-check"
        :aria-label="t('band_space_files.detail.confirm_rename')"
        size="small"
        severity="primary"
        :loading="saving"
        @click="commit"
      />
      <Button
        icon="pi pi-times"
        :aria-label="t('band_space_files.common.cancel')"
        size="small"
        text
        severity="secondary"
        :disabled="saving"
        @click="editing = false"
      />
    </template>
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import { nextTick, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

/** A file's name with its pencil, turning into a field to rename it: the drawer's head on a computer, its body on a phone. */
const props = defineProps({
  name: { type: String, default: '' },
  tag: { type: String, default: 'span' },
  nameClass: { type: String, default: '' },
  saving: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false }
})

const emit = defineEmits(['rename'])
const editing = defineModel('editing', { type: Boolean, default: false })

const { t } = useI18n()
const draft = ref(props.name)
const input = ref(null)

// Opened from outside too (the row's « Renommer », the phone's ⋮), so the draft is reset on every opening.
watch(editing, async (isEditing) => {
  if (!isEditing) return
  draft.value = props.name
  await nextTick()
  input.value?.$el?.focus()
})

// A live refresh can rename the file under a closed field; an open one keeps what is being typed.
watch(
  () => props.name,
  (name) => {
    if (!editing.value) draft.value = name
  }
)

function startEditing() {
  editing.value = true
}

function commit() {
  const trimmed = draft.value.trim()
  if (!trimmed || trimmed === props.name) {
    editing.value = false
    return
  }
  emit('rename', trimmed)
}
</script>
