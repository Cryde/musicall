<template>
  <div class="min-w-0">
    <div v-if="!editing" class="min-w-0">
      <button
        type="button"
        :class="[
          'group w-full text-left rounded-md -mx-1 px-1 py-0.5 hover:bg-surface-100 dark:hover:bg-surface-800 disabled:hover:bg-transparent disabled:cursor-default',
          multiline ? 'whitespace-pre-line' : 'truncate',
          isEmpty && 'italic text-surface-600 dark:text-surface-300'
        ]"
        :disabled="readonly"
        :aria-label="readonly ? undefined : `${label} : ${isEmpty ? 'vide' : display}. Modifier`"
        @click="startEditing"
      >
        <slot :value="modelValue">{{ isEmpty ? (readonly ? '—' : placeholder) : display }}</slot>
        <i
          v-if="!readonly"
          class="pi pi-pencil text-xs ml-1.5 opacity-0 group-hover:opacity-60 group-focus:opacity-60"
          aria-hidden="true"
        />
      </button>
    </div>

    <form v-else class="flex flex-col gap-1" @submit.prevent="commit">
      <Textarea
        v-if="multiline"
        ref="field"
        v-model="draft"
        rows="4"
        class="w-full"
        :aria-label="label"
        :invalid="!!error"
        :aria-describedby="error ? errorId : undefined"
        @keydown.esc.prevent="cancel"
        @keydown.enter.ctrl.prevent="commit"
        @blur="commit"
      />
      <InputText
        v-else
        ref="field"
        v-model="draft"
        :inputmode="kind === 'number' ? 'numeric' : undefined"
        :placeholder="kind === 'duration' ? '3:47' : undefined"
        :class="['w-full', inputClass]"
        :aria-label="label"
        :invalid="!!error"
        :aria-describedby="error ? errorId : undefined"
        @keydown.esc.prevent="cancel"
        @blur="commit"
      />
      <small v-if="error" :id="errorId" role="alert" class="text-red-600 dark:text-red-400">{{ error }}</small>
      <small v-else-if="kind === 'duration'" class="text-surface-600 dark:text-surface-300">Minutes et secondes, par exemple 3:47.</small>
      <small v-else-if="multiline" class="text-surface-600 dark:text-surface-300">Ctrl + Entrée pour enregistrer, Échap pour annuler.</small>
    </form>
  </div>
</template>

<script setup>
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import { computed, nextTick, ref, useId } from 'vue'
import { parseInlineFieldValue } from '../../../../utils/inlineFieldValue.js'
import { formatDuration } from '../../../../utils/setlistDuration.js'

/**
 * A value of a song that turns into its field on a click (#1067): it saves on leaving the field or
 * on Enter, and Escape puts it back. `save` is the caller's write; a refusal keeps the field open
 * with the server's message under it, so nothing typed is lost.
 */
const props = defineProps({
  modelValue: { type: [String, Number], default: null },
  label: { type: String, required: true },
  /** 'text', 'number' (a whole number) or 'duration' (seconds, typed as mm:ss). */
  kind: { type: String, default: 'text' },
  multiline: { type: Boolean, default: false },
  /** Refused locally when left empty, the way a title is. */
  required: { type: Boolean, default: false },
  placeholder: { type: String, default: 'Ajouter…' },
  inputClass: { type: String, default: '' },
  readonly: { type: Boolean, default: false },
  /** (value) => Promise; rejects with the error handleApiError builds. */
  save: { type: Function, required: true }
})

const editing = ref(false)
const draft = ref('')
const error = ref(null)
const saving = ref(false)
const field = ref(null)
const errorId = useId()

const isEmpty = computed(() => props.modelValue === null || props.modelValue === '')
const display = computed(() =>
  props.kind === 'duration' ? formatDuration(props.modelValue) : props.modelValue
)

function asText(value) {
  if (value === null || value === undefined) return ''
  return props.kind === 'duration' ? formatDuration(value) : String(value)
}

async function startEditing() {
  if (props.readonly) return
  draft.value = asText(props.modelValue)
  error.value = null
  editing.value = true
  await nextTick()
  const element = field.value?.$el
  ;(element?.tagName === 'TEXTAREA' || element?.tagName === 'INPUT'
    ? element
    : element?.querySelector('input, textarea')
  )?.focus()
}

// Not while a save is out: it cannot be taken back, and the value would flip to it a moment later.
function cancel() {
  if (saving.value) return
  editing.value = false
  error.value = null
}

async function commit() {
  if (!editing.value || saving.value) return
  const parsed = parseInlineFieldValue(draft.value, { kind: props.kind, required: props.required })
  if (parsed.error) {
    error.value = parsed.error
    return
  }
  if (parsed.value === (props.modelValue ?? null)) {
    cancel()
    return
  }
  saving.value = true
  try {
    await props.save(parsed.value)
    saving.value = false
    cancel()
  } catch (e) {
    error.value = e.message
  } finally {
    saving.value = false
  }
}
</script>
