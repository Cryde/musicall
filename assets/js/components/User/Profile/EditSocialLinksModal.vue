<template>
  <Dialog
    v-model:visible="isVisible"
    modal
    header="Liens sociaux"
    :style="{ width: '40rem' }"
    :closable="!isProcessing"
    :closeOnEscape="!isProcessing"
  >
    <div v-if="isLoading" class="flex justify-center py-8">
      <i class="pi pi-spin pi-spinner text-2xl"></i>
    </div>

    <template v-else>
      <!-- Existing links -->
      <div v-if="socialLinks.length > 0" class="flex flex-col gap-3 mb-6">
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
            :disabled="isProcessing"
            @click="confirmDeleteLink(link)"
          />
        </div>
      </div>

      <div v-else class="text-surface-500 dark:text-surface-400 text-sm mb-6">
        Vous n'avez pas encore ajouté de liens sociaux.
      </div>

      <!-- Add new link form -->
      <div class="flex flex-col gap-3 p-4 rounded-lg bg-surface-50 dark:bg-surface-800">
        <h4 class="font-medium text-surface-900 dark:text-surface-0">
          Ajouter un lien
        </h4>
        <div class="flex flex-col gap-3">
          <Select
            v-model="newLinkPlatform"
            :options="availablePlatforms"
            optionLabel="label"
            optionValue="value"
            placeholder="Plateforme"
            class="w-full"
            :disabled="isProcessing"
          />
          <InputText
            v-model="newLinkUrl"
            placeholder="URL du profil"
            class="w-full"
            :disabled="isProcessing"
          />
          <Button
            label="Ajouter"
            icon="pi pi-plus"
            severity="info"
            :loading="isAddingSocialLink"
            :disabled="!canAddLink || isProcessing"
            @click="addLink"
          />
        </div>
        <small v-if="addLinkError" class="text-red-500">
          {{ addLinkError }}
        </small>
      </div>
    </template>

    <template #footer>
      <div class="flex justify-end">
        <Button
          label="Fermer"
          severity="secondary"
          :disabled="isProcessing"
          @click="handleClose"
        />
      </div>
    </template>

    <ConfirmDialog />
  </Dialog>
</template>

<script setup>
import Button from 'primevue/button'
import ConfirmDialog from 'primevue/confirmdialog'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import { useConfirm } from 'primevue/useconfirm'
import { computed, ref, watch } from 'vue'
import { useUserProfileStore } from '../../../store/user/profile.js'

const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['update:visible', 'changed'])

const userProfileStore = useUserProfileStore()
const confirm = useConfirm()

const isLoading = ref(false)
const isAddingSocialLink = ref(false)
const deletingLinkId = ref(null)

const newLinkPlatform = ref(null)
const newLinkUrl = ref('')
const addLinkError = ref('')

const isVisible = computed({
  get: () => props.visible,
  set: (value) => emit('update:visible', value)
})

const socialLinks = computed(() => userProfileStore.socialLinks || [])

const isProcessing = computed(() => {
  return isAddingSocialLink.value || deletingLinkId.value !== null
})

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

// Filter out platforms that are already used
const availablePlatforms = computed(() => {
  const usedPlatforms = socialLinks.value.map((link) => link.platform)
  return platformOptions.filter((opt) => !usedPlatforms.includes(opt.value))
})

const canAddLink = computed(() => {
  return newLinkPlatform.value && newLinkUrl.value?.trim()
})

function getPlatformIcon(platform) {
  return platformIcons[platform] || 'pi-link'
}

watch(() => props.visible, async (visible) => {
  if (visible) {
    isLoading.value = true
    addLinkError.value = ''
    newLinkPlatform.value = null
    newLinkUrl.value = ''

    try {
      await userProfileStore.loadSocialLinks()
    } finally {
      isLoading.value = false
    }
  }
})

function handleClose() {
  emit('update:visible', false)
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
    emit('changed')
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
    emit('changed')
  } catch (error) {
    addLinkError.value = error.message || 'Impossible de supprimer le lien'
  } finally {
    deletingLinkId.value = null
  }
}
</script>
