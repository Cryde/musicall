<template>
  <div class="min-w-0 flex-1 text-left">
    <MentionEditor
      ref="editor"
      v-model="content"
      :members="members"
      :aria-label="t('band_space_chat.message_list.edit')"
      editor-class="min-h-[3rem] max-h-40 rounded-2xl px-3 py-2"
      submit-on="enter"
      :disabled="isSaving"
      @submit="emit('save')"
      @keydown.esc="emit('cancel')"
    />
    <Message v-if="error" severity="error" :closable="false" class="mt-1">
      {{ error }}
    </Message>
    <div class="mt-1 flex justify-end gap-2">
      <Button
        :label="t('band_space_chat.message_list.cancel')"
        size="small"
        severity="secondary"
        text
        :disabled="isSaving"
        @click="emit('cancel')"
      />
      <Button
        :label="t('band_space_chat.message_list.save')"
        size="small"
        icon="pi pi-check"
        :loading="isSaving"
        :disabled="!canSave || isSaving"
        @click="emit('save')"
      />
    </div>
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import Message from 'primevue/message'
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import MentionEditor from '../../Global/MentionEditor.vue'

/**
 * The box that takes a message's place while its author rewrites it (#966), in the band chat and in a
 * direct conversation (#1245). The state lives in useChatMessageActions().
 */
defineProps({
  /** Who `@` suggests: the roster in the band chat, nobody in a direct conversation. */
  members: { type: Array, default: () => [] },
  isSaving: { type: Boolean, default: false },
  error: { type: String, default: '' },
  canSave: { type: Boolean, default: false }
})

const emit = defineEmits(['save', 'cancel'])
/** The stored format, `@[uuid]` tokens included, which is what the editor reads and writes. */
const content = defineModel({ type: String, default: '' })

const { t } = useI18n()
const editor = ref(null)

// At the end, because you reopen a message to add to it rather than to overwrite it.
onMounted(() => editor.value?.focusAtEnd())
</script>
