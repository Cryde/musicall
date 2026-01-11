<template>
  <div class="flex flex-col gap-6">
    <div v-if="isLoading" class="flex justify-center py-8">
      <i class="pi pi-spin pi-spinner text-2xl"></i>
    </div>

    <template v-else-if="userProfileStore.profile">
      <!-- Cover picture -->
      <div class="flex flex-col md:flex-row gap-4 py-3 border-b border-surface-200 dark:border-surface-700">
        <div class="md:w-1/3">
          <div class="text-surface-600 dark:text-surface-400 font-medium">
            Photo de couverture
          </div>
          <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">
            Format recommandé : 1200x400 pixels
          </p>
        </div>
        <div class="md:w-2/3 flex flex-col gap-3">
          <!-- Current cover preview -->
          <div
            v-if="userProfileStore.profile.cover_picture_url"
            class="relative rounded-lg overflow-hidden"
          >
            <img
              :src="userProfileStore.profile.cover_picture_url"
              alt="Photo de couverture actuelle"
              class="w-full"
            />
            <div class="absolute inset-0 bg-black/40 opacity-0 hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
              <Button
                icon="pi pi-trash"
                severity="danger"
                rounded
                :loading="isDeletingCoverPicture"
                @click="confirmDeleteCoverPicture"
              />
            </div>
          </div>
          <div
            v-else
            class="flex items-center justify-center h-32 md:h-40 rounded-lg bg-surface-100 dark:bg-surface-800 border-2 border-dashed border-surface-300 dark:border-surface-600"
          >
            <div class="text-center text-surface-500 dark:text-surface-400">
              <i class="pi pi-image text-2xl mb-2" />
              <p class="text-sm">Aucune photo de couverture</p>
            </div>
          </div>
          <!-- Upload button -->
          <div class="flex items-center gap-3">
            <input
              ref="coverPictureInputRef"
              type="file"
              accept="image/*"
              class="hidden"
              @change="handleCoverPictureSelect"
            />
            <Button
              label="Choisir une image"
              icon="pi pi-upload"
              severity="secondary"
              @click="coverPictureInputRef?.click()"
            />
          </div>
          <small class="text-surface-500 dark:text-surface-400">
            L'image sera recadrée au format 3:1 (bannière).
          </small>
        </div>
      </div>

      <!-- Display name -->
      <div class="flex flex-col md:flex-row md:items-center gap-2 md:gap-4 py-3 border-b border-surface-200 dark:border-surface-700">
        <div class="md:w-1/3">
          <div class="text-surface-600 dark:text-surface-400 font-medium">
            Nom d'affichage
          </div>
          <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">
            Optionnel. Si vide, votre nom d'utilisateur sera affiché.
          </p>
        </div>
        <div class="md:w-2/3">
          <InputText
            v-model="displayName"
            placeholder="Votre nom ou pseudo..."
            :disabled="isUpdating"
            class="w-full md:w-80"
            :maxlength="100"
          />
        </div>
      </div>

      <!-- Bio -->
      <div class="flex flex-col md:flex-row gap-2 md:gap-4 py-3 border-b border-surface-200 dark:border-surface-700">
        <div class="md:w-1/3 text-surface-600 dark:text-surface-400 font-medium">
          Biographie
        </div>
        <div class="md:w-2/3 flex flex-col gap-2">
          <Textarea
            v-model="bio"
            placeholder="Parlez de vous..."
            :disabled="isUpdating"
            rows="4"
            class="w-full"
            :maxlength="2000"
          />
          <small class="text-surface-500 dark:text-surface-400">
            {{ bio?.length || 0 }} / 2000 caractères
          </small>
        </div>
      </div>

      <!-- Location -->
      <div class="flex flex-col md:flex-row md:items-center gap-2 md:gap-4 py-3 border-b border-surface-200 dark:border-surface-700">
        <div class="md:w-1/3 text-surface-600 dark:text-surface-400 font-medium">
          Localisation
        </div>
        <div class="md:w-2/3">
          <InputText
            v-model="location"
            placeholder="Votre ville, région..."
            :disabled="isUpdating"
            class="w-full md:w-80"
            :maxlength="255"
          />
        </div>
      </div>

      <!-- Save button -->
      <div class="flex justify-end">
        <Button
          label="Enregistrer"
          icon="pi pi-check"
          :loading="isUpdating"
          :disabled="!hasChanges"
          @click="saveProfile"
        />
      </div>

      <!-- Social links section -->
      <div class="pt-6 border-t border-surface-200 dark:border-surface-700">
        <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-0 mb-4">
          Liens sociaux
        </h3>

        <div v-if="isLoadingSocialLinks" class="flex justify-center py-4">
          <i class="pi pi-spin pi-spinner text-xl"></i>
        </div>

        <template v-else>
          <!-- Existing links -->
          <div v-if="socialLinks.length > 0" class="flex flex-col gap-3 mb-4">
            <div
              v-for="link in socialLinks"
              :key="link.id"
              class="flex items-center gap-3 p-3 rounded-lg bg-surface-50 dark:bg-surface-800"
            >
              <i :class="['pi', getPlatformIcon(link.platform), 'text-lg text-surface-600 dark:text-surface-400']" />
              <div class="flex-1 min-w-0">
                <span class="font-medium text-surface-900 dark:text-surface-0">
                  {{ link.platform_label }}
                </span>
                <a
                  :href="link.url"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="block text-sm text-primary-600 dark:text-primary-400 truncate hover:underline"
                >
                  {{ link.url }}
                </a>
              </div>
              <Button
                icon="pi pi-trash"
                severity="danger"
                text
                rounded
                size="small"
                :loading="deletingLinkId === link.id"
                @click="confirmDeleteLink(link)"
              />
            </div>
          </div>

          <div v-else class="text-surface-500 dark:text-surface-400 text-sm mb-4">
            Vous n'avez pas encore ajouté de liens sociaux.
          </div>

          <!-- Add new link form -->
          <div class="flex flex-col gap-3 p-4 rounded-lg bg-surface-50 dark:bg-surface-800">
            <h4 class="font-medium text-surface-900 dark:text-surface-0">
              Ajouter un lien
            </h4>
            <div class="flex flex-col md:flex-row gap-3">
              <Select
                v-model="newLinkPlatform"
                :options="platformOptions"
                optionLabel="label"
                optionValue="value"
                placeholder="Plateforme"
                class="w-full md:w-48"
                :disabled="isAddingSocialLink"
              />
              <InputText
                v-model="newLinkUrl"
                placeholder="URL du profil"
                class="flex-1"
                :disabled="isAddingSocialLink"
              />
              <Button
                label="Ajouter"
                icon="pi pi-plus"
                severity="info"
                :loading="isAddingSocialLink"
                :disabled="!canAddLink"
                @click="addLink"
              />
            </div>
            <small v-if="addLinkError" class="text-red-500">
              {{ addLinkError }}
            </small>
          </div>
        </template>
      </div>
    </template>

    <ConfirmDialog />

    <CoverPictureModal
      v-model:visible="showCoverPictureModal"
      :image="coverPictureImage"
      @saved="handleCoverPictureSaved"
    />
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import ConfirmDialog from 'primevue/confirmdialog'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref, watch } from 'vue'
import { useUserProfileStore } from '../../../store/user/profile.js'
import CoverPictureModal from './CoverPictureModal.vue'

const userProfileStore = useUserProfileStore()
const confirm = useConfirm()
const toast = useToast()

const isLoading = ref(true)
const isUpdating = ref(false)
const isLoadingSocialLinks = ref(true)
const isAddingSocialLink = ref(false)
const deletingLinkId = ref(null)
const isDeletingCoverPicture = ref(false)

// Cover picture modal
const showCoverPictureModal = ref(false)
const coverPictureImage = ref(null)
const coverPictureInputRef = ref(null)

// Profile form
const displayName = ref('')
const bio = ref('')
const location = ref('')

// Original values for change detection
const originalDisplayName = ref('')
const originalBio = ref('')
const originalLocation = ref('')

// Social links
const newLinkPlatform = ref(null)
const newLinkUrl = ref('')
const addLinkError = ref('')

const socialLinks = computed(() => userProfileStore.socialLinks || [])

const platformOptions = [
  { value: 'youtube', label: 'YouTube' },
  { value: 'soundcloud', label: 'SoundCloud' },
  { value: 'instagram', label: 'Instagram' },
  { value: 'facebook', label: 'Facebook' },
  { value: 'twitter', label: 'X (Twitter)' },
  { value: 'tiktok', label: 'TikTok' },
  { value: 'spotify', label: 'Spotify' },
  { value: 'bandcamp', label: 'Bandcamp' },
  { value: 'website', label: 'Site web' }
]

const platformIcons = {
  youtube: 'pi-youtube',
  soundcloud: 'pi-cloud',
  instagram: 'pi-instagram',
  facebook: 'pi-facebook',
  twitter: 'pi-twitter',
  tiktok: 'pi-video',
  spotify: 'pi-spotify',
  bandcamp: 'pi-headphones',
  website: 'pi-globe'
}

function getPlatformIcon(platform) {
  return platformIcons[platform] || 'pi-link'
}

const hasChanges = computed(() => {
  return displayName.value !== originalDisplayName.value ||
    bio.value !== originalBio.value ||
    location.value !== originalLocation.value
})

const canAddLink = computed(() => {
  return newLinkPlatform.value && newLinkUrl.value?.trim()
})

function setFormValues(profile) {
  displayName.value = profile?.display_name || ''
  bio.value = profile?.bio || ''
  location.value = profile?.location || ''
  originalDisplayName.value = displayName.value
  originalBio.value = bio.value
  originalLocation.value = location.value
}

watch(
  () => userProfileStore.profile,
  (profile) => {
    if (profile) {
      setFormValues(profile)
    }
  }
)

async function loadData() {
  isLoading.value = true
  isLoadingSocialLinks.value = true

  try {
    await userProfileStore.loadProfile()
    setFormValues(userProfileStore.profile)
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: 'Erreur',
      detail: 'Impossible de charger le profil',
      life: 5000
    })
  } finally {
    isLoading.value = false
  }

  try {
    await userProfileStore.loadSocialLinks()
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: 'Erreur',
      detail: 'Impossible de charger les liens sociaux',
      life: 5000
    })
  } finally {
    isLoadingSocialLinks.value = false
  }
}

async function saveProfile() {
  isUpdating.value = true

  try {
    await userProfileStore.updateProfile({
      display_name: displayName.value || null,
      bio: bio.value || null,
      location: location.value || null
    })
    originalDisplayName.value = displayName.value
    originalBio.value = bio.value
    originalLocation.value = location.value
    toast.add({
      severity: 'success',
      summary: 'Profil mis à jour',
      detail: 'Vos informations ont été enregistrées avec succès',
      life: 5000
    })
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: 'Erreur',
      detail: error.message || 'Impossible de mettre à jour le profil',
      life: 5000
    })
  } finally {
    isUpdating.value = false
  }
}

async function addLink() {
  if (!canAddLink.value) return

  addLinkError.value = ''
  isAddingSocialLink.value = true

  try {
    await userProfileStore.addSocialLink({
      platform: newLinkPlatform.value,
      url: newLinkUrl.value.trim()
    })
    newLinkPlatform.value = null
    newLinkUrl.value = ''
    toast.add({
      severity: 'success',
      summary: 'Lien ajouté',
      detail: 'Le lien a été ajouté à votre profil',
      life: 5000
    })
  } catch (error) {
    addLinkError.value = error.message || "Impossible d'ajouter le lien"
  } finally {
    isAddingSocialLink.value = false
  }
}

function confirmDeleteLink(link) {
  confirm.require({
    message: `Êtes-vous sûr de vouloir supprimer le lien ${link.platform_label} ?`,
    header: 'Confirmation',
    icon: 'pi pi-exclamation-triangle',
    rejectLabel: 'Annuler',
    acceptLabel: 'Supprimer',
    acceptClass: 'p-button-danger',
    accept: async () => {
      await deleteLink(link)
    }
  })
}

async function deleteLink(link) {
  deletingLinkId.value = link.id

  try {
    await userProfileStore.deleteSocialLink(link.id)
    toast.add({
      severity: 'success',
      summary: 'Lien supprimé',
      detail: 'Le lien a été supprimé de votre profil',
      life: 5000
    })
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: 'Erreur',
      detail: error.message || 'Impossible de supprimer le lien',
      life: 5000
    })
  } finally {
    deletingLinkId.value = null
  }
}

function handleCoverPictureSelect(event) {
  const file = event.target.files[0]
  if (!file) return

  const reader = new FileReader()
  reader.onload = (e) => {
    coverPictureImage.value = e.target.result
    showCoverPictureModal.value = true
  }
  reader.readAsDataURL(file)

  // Reset input so the same file can be selected again
  event.target.value = ''
}

function handleCoverPictureSaved() {
  toast.add({
    severity: 'success',
    summary: 'Photo mise à jour',
    detail: 'Votre photo de couverture a été mise à jour',
    life: 5000
  })
}

function confirmDeleteCoverPicture() {
  confirm.require({
    message: 'Êtes-vous sûr de vouloir supprimer votre photo de couverture ?',
    header: 'Confirmation',
    icon: 'pi pi-exclamation-triangle',
    rejectLabel: 'Annuler',
    acceptLabel: 'Supprimer',
    acceptClass: 'p-button-danger',
    accept: async () => {
      await deleteCoverPicture()
    }
  })
}

async function deleteCoverPicture() {
  isDeletingCoverPicture.value = true

  try {
    await userProfileStore.deleteCoverPicture()
    toast.add({
      severity: 'success',
      summary: 'Photo supprimée',
      detail: 'Votre photo de couverture a été supprimée',
      life: 5000
    })
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: 'Erreur',
      detail: error.message || 'Impossible de supprimer la photo de couverture',
      life: 5000
    })
  } finally {
    isDeletingCoverPicture.value = false
  }
}

onMounted(() => {
  loadData()
})
</script>
