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
            {{ t('profile.settings.profile_general.cover') }}
          </div>
          <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">
            {{ t('profile.settings.profile_general.cover_format') }}
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
              :alt="t('profile.settings.profile_general.cover_alt')"
              class="w-full"
            />
            <div class="absolute inset-0 bg-black/40 opacity-0 hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
              <Button
                icon="pi pi-trash"
                :aria-label="t('profile.settings.profile_general.cover_delete')"
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
              <p class="text-sm">{{ t('profile.settings.profile_general.cover_empty') }}</p>
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
              :label="t('profile.settings.profile_general.cover_choose')"
              icon="pi pi-upload"
              severity="secondary"
              @click="coverPictureInputRef?.click()"
            />
          </div>
          <small class="text-surface-500 dark:text-surface-400">
            {{ t('profile.settings.profile_general.cover_crop_help') }}
          </small>
        </div>
      </div>

      <!-- Display name -->
      <div class="flex flex-col md:flex-row md:items-center gap-2 md:gap-4 py-3 border-b border-surface-200 dark:border-surface-700">
        <div class="md:w-1/3">
          <div class="text-surface-600 dark:text-surface-400 font-medium">
            {{ t('profile.settings.profile_general.display_name') }}
          </div>
          <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">
            {{ t('profile.settings.profile_general.display_name_help') }}
          </p>
        </div>
        <div class="md:w-2/3">
          <InputText
            v-model="displayName"
            :placeholder="t('profile.settings.profile_general.display_name_placeholder')"
            :disabled="isUpdating"
            class="w-full md:w-80"
            :maxlength="100"
          />
        </div>
      </div>

      <!-- Bio -->
      <div class="flex flex-col md:flex-row gap-2 md:gap-4 py-3 border-b border-surface-200 dark:border-surface-700">
        <div class="md:w-1/3 text-surface-600 dark:text-surface-400 font-medium">
          {{ t('profile.settings.profile_general.bio') }}
        </div>
        <div class="md:w-2/3 flex flex-col gap-2">
          <Textarea
            v-model="bio"
            :placeholder="t('profile.settings.profile_general.bio_placeholder')"
            :disabled="isUpdating"
            rows="4"
            class="w-full"
            :maxlength="2000"
          />
          <small class="text-surface-500 dark:text-surface-400">
            {{ t('profile.settings.profile_general.bio_count', { count: bio?.length || 0 }) }}
          </small>
        </div>
      </div>

      <!-- Location -->
      <div class="flex flex-col md:flex-row md:items-center gap-2 md:gap-4 py-3 border-b border-surface-200 dark:border-surface-700">
        <label for="settings-location" class="md:w-1/3 text-surface-600 dark:text-surface-400 font-medium">
          {{ t('profile.settings.profile_general.location') }}
        </label>
        <div class="md:w-2/3">
          <CityAutoComplete
            v-model="location"
            input-id="settings-location"
            :placeholder="t('profile.settings.profile_general.location_placeholder')"
            :disabled="isUpdating"
            class="w-full md:w-80"
          />
          <small class="block mt-1 text-surface-500 dark:text-surface-400">
            {{ t('profile.settings.profile_general.location_help') }}
          </small>
        </div>
      </div>

      <!-- Save button -->
      <div class="flex justify-end">
        <Button
          :label="t('profile.settings.profile_general.save')"
          icon="pi pi-check"
          :loading="isUpdating"
          :disabled="!hasChanges"
          @click="saveProfile"
        />
      </div>

      <!-- Social links section -->
      <div class="pt-6 border-t border-surface-200 dark:border-surface-700">
        <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-0 mb-4">
          {{ t('profile.settings.profile_general.social_links') }}
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
            {{ t('profile.settings.profile_general.social_links_empty') }}
          </div>

          <!-- Add new link form -->
          <div class="flex flex-col gap-3 p-4 rounded-lg bg-surface-50 dark:bg-surface-800">
            <h4 class="font-medium text-surface-900 dark:text-surface-0">
              {{ t('profile.settings.profile_general.social_links_add_title') }}
            </h4>
            <div class="flex flex-col md:flex-row gap-3">
              <Select
                v-model="newLinkPlatform"
                :options="platformOptions"
                optionLabel="label"
                optionValue="value"
                :placeholder="t('profile.settings.profile_general.platform_placeholder')"
                class="w-full md:w-48"
                :disabled="isAddingSocialLink"
              />
              <InputText
                v-model="newLinkUrl"
                :placeholder="t('profile.settings.profile_general.url_placeholder')"
                class="flex-1"
                :disabled="isAddingSocialLink"
              />
              <Button
                :label="t('profile.settings.profile_general.add')"
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

    <CoverPictureModal
      v-model:visible="showCoverPictureModal"
      :image="coverPictureImage"
      @saved="handleCoverPictureSaved"
    />
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import CityAutoComplete from '../../../components/Global/CityAutoComplete.vue'
import { useUserProfileStore } from '../../../store/user/profile.js'
import { editedLocationPayload, locationFieldValue } from '../../../utils/profileLocation.js'
import CoverPictureModal from './CoverPictureModal.vue'

const userProfileStore = useUserProfileStore()
const confirm = useConfirm()
const toast = useToast()
const { t } = useI18n()

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
const location = ref(null)

// Original values for change detection
const originalDisplayName = ref('')
const originalBio = ref('')
const originalLocation = ref(null)

// Social links
const newLinkPlatform = ref(null)
const newLinkUrl = ref('')
const addLinkError = ref('')

const socialLinks = computed(() => userProfileStore.socialLinks || [])

const platformOptions = computed(() => [
  { value: 'youtube', label: t('profile.platform.youtube') },
  { value: 'soundcloud', label: t('profile.platform.soundcloud') },
  { value: 'instagram', label: t('profile.platform.instagram') },
  { value: 'facebook', label: t('profile.platform.facebook') },
  { value: 'twitter', label: t('profile.platform.x_twitter') },
  { value: 'tiktok', label: t('profile.platform.tiktok') },
  { value: 'spotify', label: t('profile.platform.spotify') },
  { value: 'bandcamp', label: t('profile.platform.bandcamp') },
  { value: 'website', label: t('profile.platform.website') }
])

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
  return (
    displayName.value !== originalDisplayName.value ||
    bio.value !== originalBio.value ||
    editedLocationPayload(location.value, originalLocation.value) !== null
  )
})

const canAddLink = computed(() => {
  return newLinkPlatform.value && newLinkUrl.value?.trim()
})

function setFormValues(profile) {
  displayName.value = profile?.display_name || ''
  bio.value = profile?.bio || ''
  location.value = locationFieldValue(profile)
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
      summary: t('profile.settings.profile_general.error'),
      detail: t('profile.settings.profile_general.load_error'),
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
      summary: t('profile.settings.profile_general.error'),
      detail: t('profile.settings.profile_general.social_links_load_error'),
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
      ...editedLocationPayload(location.value, originalLocation.value)
    })
    originalDisplayName.value = displayName.value
    originalBio.value = bio.value
    originalLocation.value = location.value
    toast.add({
      severity: 'success',
      summary: t('profile.settings.profile_general.saved'),
      detail: t('profile.settings.profile_general.saved_detail'),
      life: 5000
    })
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: t('profile.settings.profile_general.error'),
      detail: error.message || t('profile.settings.profile_general.save_error'),
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
      summary: t('profile.settings.profile_general.link_added'),
      detail: t('profile.settings.profile_general.link_added_detail'),
      life: 5000
    })
  } catch (error) {
    addLinkError.value = error.message || t('profile.settings.profile_general.link_add_error')
  } finally {
    isAddingSocialLink.value = false
  }
}

function confirmDeleteLink(link) {
  confirm.require({
    message: t('profile.settings.profile_general.link_delete_confirm', {
      platform: link.platform_label
    }),
    header: t('profile.settings.profile_general.confirm_header'),
    icon: 'pi pi-exclamation-triangle',
    rejectLabel: t('profile.settings.profile_general.cancel'),
    acceptLabel: t('profile.settings.profile_general.delete'),
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
      summary: t('profile.settings.profile_general.link_deleted'),
      detail: t('profile.settings.profile_general.link_deleted_detail'),
      life: 5000
    })
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: t('profile.settings.profile_general.error'),
      detail: error.message || t('profile.settings.profile_general.link_delete_error'),
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
    summary: t('profile.settings.profile_general.cover_saved'),
    detail: t('profile.settings.profile_general.cover_saved_detail'),
    life: 5000
  })
}

function confirmDeleteCoverPicture() {
  confirm.require({
    message: t('profile.settings.profile_general.cover_delete_confirm'),
    header: t('profile.settings.profile_general.confirm_header'),
    icon: 'pi pi-exclamation-triangle',
    rejectLabel: t('profile.settings.profile_general.cancel'),
    acceptLabel: t('profile.settings.profile_general.delete'),
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
      summary: t('profile.settings.profile_general.cover_deleted'),
      detail: t('profile.settings.profile_general.cover_deleted_detail'),
      life: 5000
    })
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: t('profile.settings.profile_general.error'),
      detail: error.message || t('profile.settings.profile_general.cover_delete_error'),
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
