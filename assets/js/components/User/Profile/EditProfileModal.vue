<template>
  <Dialog
    v-model:visible="isVisible"
    modal
    header="Modifier le profil"
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
          Nom d'affichage
        </label>
        <InputText
          id="displayName"
          v-model="displayName"
          placeholder="Votre nom ou pseudo..."
          :disabled="isSaving"
          :maxlength="100"
          class="w-full"
        />
        <small class="text-surface-500">
          Optionnel. Si vide, votre nom d'utilisateur sera affiché.
        </small>
      </div>

      <!-- Bio -->
      <div class="flex flex-col gap-2">
        <label for="bio" class="font-medium text-surface-900 dark:text-surface-0">
          Bio
        </label>
        <Textarea
          id="bio"
          v-model="bio"
          placeholder="Parlez de vous..."
          :disabled="isSaving"
          rows="4"
          :maxlength="2000"
          class="w-full"
        />
        <small class="text-surface-500">{{ bio?.length || 0 }} / 2000 caractères</small>
      </div>

      <!-- Location -->
      <div class="flex flex-col gap-2">
        <label for="location" class="font-medium text-surface-900 dark:text-surface-0">
          Localisation
        </label>
        <InputText
          id="location"
          v-model="location"
          placeholder="Votre ville, région..."
          :disabled="isSaving"
          :maxlength="255"
          class="w-full"
        />
      </div>

      <!-- Profile visibility -->
      <div class="flex items-center justify-between">
        <div class="flex flex-col gap-1">
          <span class="font-medium text-surface-900 dark:text-surface-0">
            Profil public
          </span>
          <small class="text-surface-500">
            Votre profil sera visible par tous les visiteurs
          </small>
        </div>
        <ToggleSwitch v-model="isPublic" :disabled="isSaving" />
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end gap-2">
        <Button
          label="Annuler"
          severity="secondary"
          :disabled="isSaving"
          @click="handleClose"
        />
        <Button
          label="Enregistrer"
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
import { useUserProfileStore } from '../../../store/user/profile.js'

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

const userProfileStore = useUserProfileStore()

const displayName = ref('')
const bio = ref('')
const location = ref('')
const isPublic = ref(true)
const error = ref('')
const isSaving = ref(false)

const isVisible = computed({
  get: () => props.visible,
  set: (value) => emit('update:visible', value)
})

watch(() => props.visible, (visible) => {
  if (visible) {
    displayName.value = props.initialDisplayName || ''
    bio.value = props.initialBio || ''
    location.value = props.initialLocation || ''
    isPublic.value = props.initialIsPublic
    error.value = ''
  }
})

function handleClose() {
  error.value = ''
  emit('update:visible', false)
}

async function handleSave() {
  error.value = ''
  isSaving.value = true

  try {
    await userProfileStore.updateProfile({
      display_name: displayName.value || null,
      bio: bio.value || null,
      location: location.value || null,
      is_public: isPublic.value
    })
    emit('update:visible', false)
    emit('saved')
  } catch (e) {
    if (e.violations?.length) {
      error.value = e.violations.map((v) => v.message).join('. ')
    } else {
      error.value = e.message || 'Une erreur est survenue'
    }
  } finally {
    isSaving.value = false
  }
}
</script>
