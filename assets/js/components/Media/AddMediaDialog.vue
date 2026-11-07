<template>
  <Dialog
    v-model:visible="visible"
    modal
    :header="t('profile.media.add_creation')"
    :style="{ width: '500px' }"
    @hide="reset"
  >
    <div class="flex flex-col gap-4">
      <div v-if="targets.length > 1" class="flex flex-col gap-2">
        <span :id="`${fieldId}-target`" class="text-sm font-medium text-surface-700 dark:text-surface-300">
          {{ t('profile.media.target_label') }}
        </span>
        <SelectButton
          v-model="target"
          :options="targetOptions"
          optionLabel="label"
          optionValue="value"
          :allowEmpty="false"
          :aria-labelledby="`${fieldId}-target`"
        />
      </div>

      <div>
        <label :for="`${fieldId}-url`" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-2">
          {{ t('profile.media.url_label') }}
        </label>
        <InputText
          :id="`${fieldId}-url`"
          v-model="url"
          :placeholder="t('profile.media.url_placeholder')"
          class="w-full"
          :invalid="!!urlError"
        />
        <p v-if="urlError" class="text-xs text-red-500 mt-1">
          {{ urlError }}
        </p>
        <p v-else class="text-xs text-surface-500 dark:text-surface-400 mt-1">
          {{ t('profile.media.supported_platforms') }}
        </p>
      </div>

      <div>
        <label :for="`${fieldId}-title`" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-2">
          {{ t('profile.media.title_label') }}
        </label>
        <InputText
          :id="`${fieldId}-title`"
          v-model="title"
          :placeholder="t('profile.media.title_placeholder')"
          class="w-full"
        />
      </div>

      <div v-if="detectedPlatform" class="p-4 bg-surface-100 dark:bg-surface-800 rounded-lg">
        <div class="flex items-center gap-3">
          <Tag :value="getMediaPlatformLabel(detectedPlatform)" :severity="mediaPlatformSeverity(detectedPlatform)" />
          <span class="text-sm text-surface-600 dark:text-surface-300">
            {{ t('profile.media.detected_platform') }}
          </span>
        </div>
      </div>
    </div>

    <template #footer>
      <Button :label="t('profile.media.cancel')" severity="secondary" text @click="visible = false" />
      <Button
        :label="t('profile.media.add')"
        icon="pi pi-plus"
        :disabled="!url || !target"
        :loading="isAdding"
        @click="handleAdd"
      />
    </template>
  </Dialog>
</template>

<script setup>
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import SelectButton from 'primevue/selectbutton'
import Tag from 'primevue/tag'
import { computed, ref, useId, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { getMediaPlatformLabel } from '../../constants/teacherProfile.js'
import { useMusicianProfileMediaStore } from '../../store/user/musicianProfileMedia.js'
import { useTeacherProfileMediaStore } from '../../store/user/teacherProfileMedia.js'
import { detectMediaPlatform, mediaPlatformSeverity } from '../../utils/mediaEmbed.js'

/**
 * Adds a media to the member's musician or teacher profile. `targets` lists the profiles it can go to: with two,
 * the member picks one (#1317).
 */
const props = defineProps({
  /** `musician` and/or `teacher`. */
  targets: { type: Array, required: true }
})

const emit = defineEmits(['added'])
const visible = defineModel('visible', { type: Boolean, default: false })
const { t } = useI18n()
const fieldId = useId()

const stores = {
  musician: useMusicianProfileMediaStore(),
  teacher: useTeacherProfileMediaStore()
}
const TARGET_LABELS = {
  musician: 'profile.media.target.musician',
  teacher: 'profile.media.target.teacher'
}

const url = ref('')
const title = ref('')
const urlError = ref('')
const target = ref(null)

const targetOptions = computed(() =>
  props.targets.map((value) => ({ value, label: t(TARGET_LABELS[value]) }))
)
const detectedPlatform = computed(() => detectMediaPlatform(url.value))
const isAdding = computed(() => Boolean(target.value && stores[target.value].isAdding))

watch(url, () => {
  urlError.value = ''
})

watch(visible, (isVisible) => {
  if (isVisible) target.value = props.targets[0] ?? null
})

async function handleAdd() {
  if (!url.value || !target.value) return

  try {
    const media = await stores[target.value].addMedia(url.value, title.value || null)
    emit('added', { media, target: target.value })
    visible.value = false
  } catch (error) {
    urlError.value =
      error.message ||
      (error.isValidationError ? t('profile.media.invalid_url') : t('profile.media.error'))
  }
}

function reset() {
  url.value = ''
  title.value = ''
  urlError.value = ''
}
</script>
