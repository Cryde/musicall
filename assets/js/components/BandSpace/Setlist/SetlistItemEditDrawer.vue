<template>
  <Drawer
    v-model:visible="visible"
    position="right"
    :style="{ width: '400px' }"
    :header="item?.song?.title || item?.label || t('band_space_setlist.item_drawer.header_fallback')"
  >
    <div v-if="item" class="flex flex-col gap-4">
      <div class="text-xs text-surface-500 uppercase tracking-wide">
        {{ t('band_space_setlist.item_drawer.type', { type: typeLabel }) }}
      </div>

      <div v-if="item.type !== 'song'">
        <label class="block text-sm font-medium mb-1">{{ t('band_space_setlist.item_drawer.label_label') }}</label>
        <InputText v-model="form.label" class="w-full" :aria-label="t('band_space_setlist.item_drawer.label_label')" />
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">{{ t('band_space_setlist.item_drawer.duration_label') }}</label>
        <InputText
          v-model="durationInput"
          placeholder="3:47"
          class="w-full"
          :invalid="!!durationError"
          :aria-label="t('band_space_setlist.item_drawer.duration_aria')"
        />
        <small v-if="durationError" class="text-red-500 block mt-1">{{ durationError }}</small>
        <small v-else class="text-surface-500 block mt-1">
          {{ t('band_space_setlist.item_drawer.duration_hint') }}
          <template v-if="referenceDuration">
            {{ t('band_space_setlist.item_drawer.duration_reference_hint', { duration: formatDuration(referenceDuration) }) }}
          </template>
        </small>
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">{{ t('band_space_setlist.item_drawer.transition_label') }}</label>
        <InputText
          v-model="form.transition"
          :placeholder="t('band_space_setlist.item_drawer.transition_placeholder')"
          class="w-full"
          :aria-label="t('band_space_setlist.item_drawer.transition_label')"
        />
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">{{ t('band_space_setlist.item_drawer.note_label') }}</label>
        <Textarea v-model="form.note" rows="3" class="w-full" :aria-label="t('band_space_setlist.item_drawer.note_label')" />
      </div>

      <div class="flex justify-between gap-2 pt-2">
        <Button :label="t('band_space_setlist.common.remove')" severity="danger" outlined icon="pi pi-trash" @click="confirmRemove" />
        <div class="flex gap-2">
          <Button :label="t('band_space_setlist.common.cancel')" severity="secondary" text @click="visible = false" />
          <Button :label="t('band_space_setlist.common.save')" :loading="isSubmitting" @click="handleSave" />
        </div>
      </div>
    </div>
  </Drawer>
</template>

<script setup>
import Button from 'primevue/button'
import Drawer from 'primevue/drawer'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useBandSetlistsStore } from '../../../store/bandSpace/bandSpaceSetlists.js'
import { formatDuration, parseDurationInput } from '../../../utils/setlistDuration.js'

const props = defineProps({
  bandSpaceId: { type: String, required: true },
  setlistId: { type: String, required: true },
  item: { type: Object, default: null }
})

const emit = defineEmits(['saved', 'removed'])
const visible = defineModel('visible', { type: Boolean, default: false })

const { t } = useI18n()
const setlistsStore = useBandSetlistsStore()
const confirm = useConfirm()
const toast = useToast()

const form = ref({ label: '', transition: '', note: '' })
// The override is held as text and converted once, on save: seconds stay the stored unit, they are
// just no longer what a member has to type.
const durationInput = ref('')
const durationError = ref(null)
const isSubmitting = ref(false)

const typeLabel = computed(() => {
  switch (props.item?.type) {
    case 'song':
      return t('band_space_setlist.item_drawer.type_song')
    case 'interlude':
      return t('band_space_setlist.item_drawer.type_interlude')
    case 'break':
      return t('band_space_setlist.item_drawer.type_break')
    case 'talk':
      return t('band_space_setlist.item_drawer.type_talk')
    default:
      return '—'
  }
})

const referenceDuration = computed(() => props.item?.song?.reference_duration ?? null)

// Reopening the drawer reseeds it, not only switching item: closing on a refused duration and
// coming back to the same card must not show the refusal again.
watch(
  [visible, () => props.item],
  ([isVisible, item]) => {
    if (isVisible && item) {
      form.value = {
        label: item.label ?? '',
        transition: item.transition ?? '',
        note: item.note ?? ''
      }
      durationInput.value = formatDuration(item.duration_override)
      durationError.value = null
    }
  },
  { immediate: true }
)

async function handleSave() {
  if (!props.item) return

  const duration = parseDurationInput(durationInput.value)
  durationError.value = duration.error
  if (duration.error) return

  isSubmitting.value = true
  try {
    await setlistsStore.updateItem(props.bandSpaceId, props.setlistId, props.item.id, {
      label: form.value.label?.trim() || null,
      duration_override: duration.seconds,
      transition: form.value.transition?.trim() || null,
      note: form.value.note?.trim() || null
    })
    toast.add({
      severity: 'success',
      summary: t('band_space_setlist.item_drawer.saved'),
      life: 3000
    })
    emit('saved')
    visible.value = false
  } catch (e) {
    toast.add({
      severity: 'error',
      summary: t('band_space_setlist.common.error'),
      detail: e.message,
      life: 5000
    })
  } finally {
    isSubmitting.value = false
  }
}

function confirmRemove() {
  if (!props.item) return
  confirm.require({
    message: t('band_space_setlist.item_drawer.remove_confirm_message'),
    header: t('band_space_setlist.common.confirm'),
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: t('band_space_setlist.common.remove'),
    rejectLabel: t('band_space_setlist.common.cancel'),
    accept: async () => {
      try {
        await setlistsStore.removeItem(props.bandSpaceId, props.setlistId, props.item.id)
        toast.add({
          severity: 'success',
          summary: t('band_space_setlist.item_drawer.removed'),
          life: 3000
        })
        emit('removed')
        visible.value = false
      } catch (e) {
        toast.add({
          severity: 'error',
          summary: t('band_space_setlist.common.error'),
          detail: e.message,
          life: 5000
        })
      }
    }
  })
}
</script>
