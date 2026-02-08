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
          <div class="md:w-2/3">
            <template v-if="!isEditingUsername">
              <div class="flex items-center gap-3">
                <span class="text-surface-900 dark:text-surface-0">
                  {{ userSettingsStore.userProfile.username }}
                </span>
                <Button
                  v-if="canChangeUsername"
                  label="Modifier"
                  icon="pi pi-pencil"
                  severity="info"
                  text
                  size="small"
                  @click="startEditUsername"
                />
              </div>
              <p v-if="!canChangeUsername && nextUsernameChangeDate" class="text-sm text-surface-500 dark:text-surface-400 mt-1">
                Vous pourrez modifier votre nom d'utilisateur le {{ nextUsernameChangeDate }}
              </p>
            </template>
            <template v-else>
              <div class="flex flex-col gap-2">
                <Message severity="warn" :closable="false" class="mb-2">
                  Attention : vous ne pourrez pas modifier votre nom d'utilisateur pendant 30 jours après ce changement.
                </Message>
                <div class="flex items-center gap-2">
                  <InputText
                    v-model="newUsername"
                    placeholder="Nouveau nom d'utilisateur"
                    class="w-full md:w-64"
                    :invalid="!!usernameError"
                  />
                  <i v-if="isCheckingUsername" class="pi pi-spin pi-spinner text-surface-500"></i>
                  <i v-else-if="isUsernameAvailable === true" class="pi pi-check-circle text-green-500"></i>
                  <i v-else-if="isUsernameAvailable === false" class="pi pi-times-circle text-red-500"></i>
                </div>
                <small v-if="usernameError" class="text-red-500">{{ usernameError }}</small>
                <small v-else-if="isUsernameAvailable === true" class="text-green-600">Ce nom d'utilisateur est disponible</small>
                <div class="flex gap-2">
                  <Button
                    label="Enregistrer"
                    icon="pi pi-check"
                    size="small"
                    :loading="userSettingsStore.isChangingUsername"
                    :disabled="isCheckingUsername || isUsernameAvailable === false"
                    @click="saveUsername"
                  />
                  <Button
                    label="Annuler"
                    icon="pi pi-times"
                    severity="secondary"
                    text
                    size="small"
                    :disabled="userSettingsStore.isChangingUsername"
                    @click="cancelEditUsername"
                  />
                </div>
              </div>
            </template>
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
              :style="getAvatarStyle(userSettingsStore.userProfile.username)"
              size="xlarge"
              shape="circle"
            />
            <div class="flex gap-2">
              <Button
                :label="userSettingsStore.userProfile.profile_picture ? 'Modifier ma photo' : 'Ajouter une photo'"
                icon="pi pi-image"
                severity="info"
                outlined
                @click="triggerFileInput"
              />
              <Button
                v-if="userSettingsStore.userProfile.profile_picture"
                label="Supprimer"
                icon="pi pi-trash"
                severity="danger"
                outlined
                :loading="userSettingsStore.isDeletingPicture"
                @click="confirmDeletePicture"
              />
            </div>
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

      <!-- Danger Zone -->
      <div class="mt-8 border border-red-300 dark:border-red-800 rounded-lg p-4">
        <h3 class="text-lg font-semibold text-red-600 dark:text-red-400 mb-2">Zone de danger</h3>
        <p class="text-sm text-surface-600 dark:text-surface-400 mb-4">
          La suppression de votre compte est irréversible. Vos publications, commentaires et messages resteront visibles
          mais seront associés à un utilisateur anonyme.
        </p>
        <Button
          label="Supprimer mon compte"
          icon="pi pi-trash"
          severity="danger"
          @click="showDeleteAccountDialog = true"
        />
      </div>
    </template>

    <Dialog
      v-model:visible="showDeleteAccountDialog"
      header="Supprimer mon compte"
      modal
      :style="{ width: '450px' }"
    >
      <div class="flex flex-col gap-4">
        <Message severity="warn" :closable="false">
          Cette action est irréversible. Toutes vos données personnelles seront supprimées.
        </Message>
        <template v-if="hasPassword">
          <p class="text-sm text-surface-600 dark:text-surface-400">
            Pour confirmer la suppression, veuillez saisir votre mot de passe.
          </p>
          <InputText
            v-model="deleteAccountPassword"
            type="password"
            placeholder="Votre mot de passe"
            class="w-full"
            :invalid="!!deleteAccountError"
          />
        </template>
        <template v-else>
          <p class="text-sm text-surface-600 dark:text-surface-400">
            Pour confirmer, veuillez saisir <span class="font-semibold">supprimer définitivement</span> ci-dessous.
          </p>
          <InputText
            v-model="deleteAccountConfirmation"
            placeholder="supprimer définitivement"
            class="w-full"
          />
        </template>
        <small v-if="deleteAccountError" class="text-red-500">{{ deleteAccountError }}</small>
      </div>
      <template #footer>
        <Button
          label="Annuler"
          severity="secondary"
          text
          @click="showDeleteAccountDialog = false"
        />
        <Button
          label="Supprimer définitivement"
          icon="pi pi-trash"
          severity="danger"
          :loading="isDeletingAccount"
          :disabled="hasPassword ? !deleteAccountPassword : !isConfirmationValid"
          @click="handleDeleteAccount"
        />
      </template>
    </Dialog>

    <ProfilePictureModal
      v-model:visible="showPictureModal"
      :image="selectedImage"
      @saved="handlePictureSaved"
    />

    <ConfirmDialog />
  </div>
</template>

<script setup>
import Avatar from 'primevue/avatar'
import Button from 'primevue/button'
import ConfirmDialog from 'primevue/confirmdialog'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import ToggleSwitch from 'primevue/toggleswitch'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, ref, watch } from 'vue'
import securityApi from '../../../api/user/security.js'
import { useDarkMode } from '../../../composables/useDarkMode.js'
import { useUserSecurityStore } from '../../../store/user/security.js'
import { useUserSettingsStore } from '../../../store/user/settings.js'
import { getAvatarStyle } from '../../../utils/avatar.js'
import ProfilePictureModal from './ProfilePictureModal.vue'

const COOLDOWN_DAYS = 30

const userSettingsStore = useUserSettingsStore()
const userSecurityStore = useUserSecurityStore()
const confirm = useConfirm()
const toast = useToast()
const { isDarkMode, setDarkMode } = useDarkMode()

const fileInput = ref(null)
const selectedImage = ref(null)
const showPictureModal = ref(false)

const isEditingUsername = ref(false)
const newUsername = ref('')
const usernameError = ref('')
const isCheckingUsername = ref(false)
const isUsernameAvailable = ref(null)
let checkUsernameTimeout = null

const canChangeUsername = computed(() => {
  const lastChange = userSettingsStore.userProfile?.username_changed_datetime
  if (!lastChange) {
    return true
  }
  const lastChangeDate = new Date(lastChange)
  const cooldownEnd = new Date(lastChangeDate.getTime() + COOLDOWN_DAYS * 24 * 60 * 60 * 1000)
  return new Date() >= cooldownEnd
})

const nextUsernameChangeDate = computed(() => {
  const lastChange = userSettingsStore.userProfile?.username_changed_datetime
  if (!lastChange) {
    return null
  }
  const lastChangeDate = new Date(lastChange)
  const cooldownEnd = new Date(lastChangeDate.getTime() + COOLDOWN_DAYS * 24 * 60 * 60 * 1000)
  return cooldownEnd.toLocaleDateString('fr-FR', {
    day: 'numeric',
    month: 'long',
    year: 'numeric'
  })
})

function startEditUsername() {
  newUsername.value = userSettingsStore.userProfile?.username || ''
  usernameError.value = ''
  isEditingUsername.value = true
}

function cancelEditUsername() {
  isEditingUsername.value = false
  newUsername.value = ''
  usernameError.value = ''
  isUsernameAvailable.value = null
  if (checkUsernameTimeout) {
    clearTimeout(checkUsernameTimeout)
  }
}

const USERNAME_REGEX = /^[a-zA-Z0-9._]+$/

function validateUsername(username) {
  if (!username || username.length < 3) {
    return 'Le nom d\'utilisateur doit au moins contenir 3 caractères'
  }
  if (username.length > 40) {
    return 'Le nom d\'utilisateur doit contenir maximum 40 caractères'
  }
  if (!USERNAME_REGEX.test(username)) {
    return 'Nom d\'utilisateur invalide : seuls les lettres, chiffres, points et underscores sont autorisés.'
  }
  return null
}

watch(newUsername, (value) => {
  usernameError.value = ''
  isUsernameAvailable.value = null

  if (checkUsernameTimeout) {
    clearTimeout(checkUsernameTimeout)
  }

  const trimmed = value?.trim()
  if (!trimmed || trimmed === userSettingsStore.userProfile?.username) {
    return
  }

  // Validate format before checking availability
  const validationError = validateUsername(trimmed)
  if (validationError) {
    usernameError.value = validationError
    return
  }

  isCheckingUsername.value = true
  checkUsernameTimeout = setTimeout(async () => {
    try {
      const result = await securityApi.checkUsernameAvailability(trimmed)
      isUsernameAvailable.value = result.available
      if (!result.available) {
        usernameError.value = 'Ce nom d\'utilisateur est déjà pris'
      }
    } catch {
      // Ignore errors, validation will happen on submit
    } finally {
      isCheckingUsername.value = false
    }
  }, 500)
})

async function saveUsername() {
  usernameError.value = ''

  const trimmed = newUsername.value?.trim()
  const validationError = validateUsername(trimmed)
  if (validationError) {
    usernameError.value = validationError
    return
  }

  if (trimmed === userSettingsStore.userProfile?.username) {
    usernameError.value = 'Le nouveau nom d\'utilisateur doit être différent de l\'actuel'
    return
  }

  try {
    await userSettingsStore.changeUsername(newUsername.value.trim())
    isEditingUsername.value = false
    newUsername.value = ''
    toast.add({
      severity: 'success',
      summary: 'Nom d\'utilisateur modifié',
      detail: 'Votre nom d\'utilisateur a été mis à jour avec succès',
      life: 5000
    })
  } catch (error) {
    usernameError.value = error.message || 'Une erreur est survenue'
  }
}

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

function confirmDeletePicture() {
  confirm.require({
    message: 'Êtes-vous sûr de vouloir supprimer votre photo de profil ?',
    header: 'Confirmation',
    icon: 'pi pi-exclamation-triangle',
    rejectLabel: 'Annuler',
    acceptLabel: 'Supprimer',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await userSettingsStore.deleteProfilePicture()
        toast.add({
          severity: 'success',
          summary: 'Photo supprimée',
          detail: 'Votre photo de profil a été supprimée avec succès',
          life: 5000
        })
      } catch {
        toast.add({
          severity: 'error',
          summary: 'Erreur',
          detail: 'Impossible de supprimer la photo de profil',
          life: 5000
        })
      }
    }
  })
}

// Delete account
const hasPassword = computed(() => userSettingsStore.userProfile?.has_password ?? true)
const showDeleteAccountDialog = ref(false)
const deleteAccountPassword = ref('')
const deleteAccountConfirmation = ref('')
const deleteAccountError = ref('')
const isDeletingAccount = ref(false)

function normalize(str) {
  return str.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim()
}

const isConfirmationValid = computed(() => normalize(deleteAccountConfirmation.value) === 'supprimer definitivement')

async function handleDeleteAccount() {
  deleteAccountError.value = ''
  isDeletingAccount.value = true
  try {
    await securityApi.deleteAccount(hasPassword.value ? deleteAccountPassword.value : null)
    showDeleteAccountDialog.value = false
    await userSecurityStore.logout()
  } catch (error) {
    if (error.isValidationError) {
      deleteAccountError.value = error.violations?.[0]?.message || 'Le mot de passe est invalide'
    } else {
      deleteAccountError.value = 'Une erreur est survenue'
    }
  } finally {
    isDeletingAccount.value = false
  }
}
</script>
