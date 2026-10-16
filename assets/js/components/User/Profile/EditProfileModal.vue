<template>
  <Dialog
    v-model:visible="isVisible"
    modal
    :header="t('profile.edit.title')"
    :style="{ width: '35rem' }"
    :closable="!isSaving"
    :closeOnEscape="!isSaving"
  >
    <Message v-if="error" severity="error" :closable="false" class="mb-4">
      {{ error }}
    </Message>

    <div class="flex flex-col gap-5">
      <!-- Display name -->
      <div class="flex flex-col gap-2">
        <label for="displayName" class="font-medium text-surface-900 dark:text-surface-0">
          {{ t('profile.edit.display_name') }}
        </label>
        <InputText
          id="displayName"
          v-model="displayName"
          :placeholder="t('profile.edit.display_name_placeholder')"
          :disabled="isSaving"
          :maxlength="100"
          class="w-full"
        />
        <small class="text-surface-500">
          {{ t('profile.edit.display_name_help') }}
        </small>
      </div>

      <!-- Bio -->
      <div class="flex flex-col gap-2">
        <label for="bio" class="font-medium text-surface-900 dark:text-surface-0">
          {{ t('profile.edit.bio') }}
        </label>
        <Textarea
          id="bio"
          v-model="bio"
          :placeholder="t('profile.edit.bio_placeholder')"
          :disabled="isSaving"
          rows="4"
          :maxlength="2000"
          class="w-full"
        />
        <small class="text-surface-500">{{ t('profile.edit.bio_count', { count: bio?.length || 0 }) }}</small>
      </div>

      <!-- Location -->
      <div class="flex flex-col gap-2">
        <label for="location" class="font-medium text-surface-900 dark:text-surface-0">
          {{ t('profile.edit.location') }}
        </label>
        <CityAutoComplete v-model="location" input-id="location" :placeholder="t('profile.edit.location_placeholder')" :disabled="isSaving" />
        <small class="text-surface-500">
          {{ t('profile.edit.location_help') }}
        </small>
      </div>

      <!-- Profile visibility -->
      <div class="flex items-center justify-between">
        <div class="flex flex-col gap-1">
          <span class="font-medium text-surface-900 dark:text-surface-0">
            {{ t('profile.edit.public') }}
          </span>
          <small class="text-surface-500">
            {{ t('profile.edit.public_help') }}
          </small>
        </div>
        <ToggleSwitch v-model="isPublic" :disabled="isSaving" />
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end gap-2">
        <Button
          :label="t('profile.edit.cancel')"
          severity="secondary"
          :disabled="isSaving"
          @click="handleClose"
        />
        <Button
          :label="t('profile.edit.save')"
          icon="pi pi-check"
          :loading="isSaving"
          @click="handleSave"
        />
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Textarea from 'primevue/textarea'
import ToggleSwitch from 'primevue/toggleswitch'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import profileApi from '../../../api/user/profile.js'
import { editedLocationPayload } from '../../../utils/profileLocation.js'
import CityAutoComplete from '../../Global/CityAutoComplete.vue'

const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  initialDisplayName: {
    type: String,
    default: null
  },
  initialBio: {
    type: String,
    default: null
  },
  /** The public profile's, so text only: the modal saves the location only when it is changed. */
  initialLocation: {
    type: String,
    default: null
  },
  initialIsPublic: {
    type: Boolean,
    default: true
  }
})

const emit = defineEmits(['update:visible', 'saved'])
const { t } = useI18n()

const displayName = ref('')
const bio = ref('')
const location = ref(null)
const isPublic = ref(true)
const error = ref('')
const isSaving = ref(false)

const isVisible = computed({
  get: () => props.visible,
  set: (value) => emit('update:visible', value)
})

watch(
  () => props.visible,
  (visible) => {
    if (visible) {
      displayName.value = props.initialDisplayName || ''
      bio.value = props.initialBio || ''
      location.value = props.initialLocation || null
      isPublic.value = props.initialIsPublic
      error.value = ''
    }
  }
)

function handleClose() {
  error.value = ''
  emit('update:visible', false)
}

async function handleSave() {
  error.value = ''
  isSaving.value = true

  try {
    // Not through the profile store: on the public profile page it holds the public profile, which
    // the edit response (no username, no pictures) would replace until the page reloads it.
    await profileApi.updateMyProfile({
      display_name: displayName.value || null,
      bio: bio.value || null,
      ...editedLocationPayload(location.value, props.initialLocation),
      is_public: isPublic.value
    })
    emit('update:visible', false)
    emit('saved')
  } catch (e) {
    if (e.violations?.length) {
      error.value = e.violations.map((v) => v.message).join('. ')
    } else {
      error.value = e.message || t('profile.edit.error')
    }
  } finally {
    isSaving.value = false
  }
}
</script>
