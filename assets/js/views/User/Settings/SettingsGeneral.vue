<template>
  <div class="flex flex-col gap-6">
    <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0">
      Paramètres généraux du compte
    </h2>

    <div v-if="userSettingsStore.isLoading" class="flex justify-center py-8">
      <i class="pi pi-spin pi-spinner text-2xl"></i>
    </div>

    <template v-else-if="userSettingsStore.userProfile">
      <div class="flex flex-col gap-4">
        <!-- Username -->
        <div class="flex flex-col md:flex-row md:items-center gap-2 md:gap-4 py-3 border-b border-surface-200 dark:border-surface-700">
          <div class="md:w-1/3 text-surface-600 dark:text-surface-400 font-medium">
            Nom d'utilisateur
          </div>
          <div class="md:w-2/3 text-surface-900 dark:text-surface-0">
            {{ userSettingsStore.userProfile.username }}
          </div>
        </div>

        <!-- Email -->
        <div class="flex flex-col md:flex-row md:items-center gap-2 md:gap-4 py-3 border-b border-surface-200 dark:border-surface-700">
          <div class="md:w-1/3 text-surface-600 dark:text-surface-400 font-medium">
            Adresse email
          </div>
          <div class="md:w-2/3 text-surface-900 dark:text-surface-0">
            {{ userSettingsStore.userProfile.email }}
          </div>
        </div>

        <!-- Profile Picture -->
        <div class="flex flex-col md:flex-row md:items-center gap-2 md:gap-4 py-3 border-b border-surface-200 dark:border-surface-700">
          <div class="md:w-1/3 text-surface-600 dark:text-surface-400 font-medium">
            Photo de profil
          </div>
          <div class="md:w-2/3 flex items-center gap-4">
            <Avatar
              v-if="userSettingsStore.userProfile.profile_picture"
              :image="profilePictureUrl"
              size="xlarge"
              shape="circle"
            />
            <Avatar
              v-else
              :label="userSettingsStore.userProfile.username.charAt(0).toUpperCase()"
              size="xlarge"
              shape="circle"
            />
            <Button
              :label="userSettingsStore.userProfile.profile_picture ? 'Modifier ma photo' : 'Ajouter une photo'"
              icon="pi pi-image"
              severity="info"
              outlined
              @click="triggerFileInput"
            />
            <input
              ref="fileInput"
              type="file"
              accept="image/*"
              class="hidden"
              @change="handleFileSelect"
            />
          </div>
        </div>

        <!-- Dark Mode -->
        <div class="flex flex-col md:flex-row md:items-center gap-2 md:gap-4 py-3">
          <div class="md:w-1/3 text-surface-600 dark:text-surface-400 font-medium">
            Mode sombre
          </div>
          <div class="md:w-2/3 flex items-center gap-3">
            <ToggleSwitch v-model="isDarkModeEnabled" />
            <span class="text-surface-600 dark:text-surface-400 text-sm">
              {{ isDarkModeEnabled ? 'Activé' : 'Désactivé' }}
            </span>
          </div>
        </div>
      </div>
    </template>

    <ProfilePictureModal
      v-model:visible="showPictureModal"
      :image="selectedImage"
      @saved="handlePictureSaved"
    />
  </div>
</template>

<script setup>
import Avatar from 'primevue/avatar'
import Button from 'primevue/button'
import ToggleSwitch from 'primevue/toggleswitch'
import { useToast } from 'primevue/usetoast'
import { computed, ref } from 'vue'
import { useDarkMode } from '../../../composables/useDarkMode.js'
import { useUserSettingsStore } from '../../../store/user/settings.js'
import ProfilePictureModal from './ProfilePictureModal.vue'

const userSettingsStore = useUserSettingsStore()
const toast = useToast()
const { isDarkMode, setDarkMode } = useDarkMode()

const fileInput = ref(null)
const selectedImage = ref(null)
const showPictureModal = ref(false)

const isDarkModeEnabled = computed({
  get: () => isDarkMode.value,
  set: (value) => setDarkMode(value)
})

const profilePictureUrl = computed(() => {
  if (userSettingsStore.userProfile?.profile_picture?.small) {
    return userSettingsStore.userProfile?.profile_picture?.small
  }
  return null
})

function triggerFileInput() {
  fileInput.value?.click()
}

function handleFileSelect(event) {
  const file = event.target.files?.[0]
  if (file) {
    const reader = new FileReader()
    reader.onload = (e) => {
      selectedImage.value = e.target.result
      showPictureModal.value = true
    }
    reader.readAsDataURL(file)
  }
  event.target.value = ''
}

function handlePictureSaved() {
  toast.add({
    severity: 'success',
    summary: 'Photo mise à jour',
    detail: 'Votre photo de profil a été mise à jour avec succès',
    life: 5000
  })
}
</script>
