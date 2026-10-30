<template>
  <Dialog v-model:visible="visible" :header="header" modal :style="{ width: 'min(26rem, 100vw)' }" @hide="reset">
    <form class="flex flex-col gap-3" @submit.prevent="submit">
      <div class="flex flex-col gap-1">
        <label :for="inputId" class="text-sm font-medium">{{ label }}</label>
        <InputText
          :id="inputId"
          v-model="title"
          autofocus
          :invalid="!!error"
          :aria-describedby="error ? `${inputId}-error` : `${inputId}-hint`"
        />
        <small v-if="error" :id="`${inputId}-error`" role="alert" class="text-red-600 dark:text-red-400">{{ error }}</small>
        <small v-else :id="`${inputId}-hint`" class="text-surface-600 dark:text-surface-300">
          {{ hint }}
        </small>
      </div>
      <div class="flex justify-end gap-2">
        <Button :label="t('band_space_setlist.common.cancel')" severity="secondary" text type="button" @click="visible = false" />
        <Button :label="t('band_space_setlist.common.create')" type="submit" :loading="isSubmitting" />
      </div>
    </form>
  </Dialog>
</template>

<script setup>
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import { ref, useId } from 'vue'
import { useI18n } from 'vue-i18n'

/**
 * Something that starts from its title alone (#1067, #1261): a song, or lyrics. The rest is filled
 * in its drawer, which the caller opens with what this emits.
 */
const props = defineProps({
  header: { type: String, required: true },
  label: { type: String, required: true },
  hint: { type: String, required: true },
  requiredMessage: { type: String, required: true },
  /** (title) => Promise of the created item; a refusal shows its message under the field. */
  create: { type: Function, required: true }
})

const emit = defineEmits(['created'])
const visible = defineModel('visible', { type: Boolean, default: false })

const { t } = useI18n()
const inputId = useId()
const title = ref('')
const error = ref(null)
const isSubmitting = ref(false)

function reset() {
  title.value = ''
  error.value = null
}

async function submit() {
  const trimmed = title.value.trim()
  if (!trimmed) {
    error.value = props.requiredMessage
    return
  }
  isSubmitting.value = true
  try {
    const created = await props.create(trimmed)
    visible.value = false
    emit('created', created)
  } catch (e) {
    error.value = e.message
  } finally {
    isSubmitting.value = false
  }
}
</script>
