<template>
  <div class="flex flex-col gap-6">
    <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0">
      Changer le mot de passe
    </h2>

    <Message v-if="successMessage" severity="success" :closable="false" class="mb-2">
      {{ successMessage }}
    </Message>

    <Message v-if="errorMessage" severity="error" :closable="false" class="mb-2">
      {{ errorMessage }}
    </Message>

    <form @submit.prevent="handleSubmit" class="flex flex-col gap-4 max-w-md">
      <div class="flex flex-col gap-2">
        <label for="oldPassword" class="text-surface-600 dark:text-surface-400 font-medium">
          Mot de passe actuel
        </label>
        <Password
          id="oldPassword"
          v-model="oldPassword"
          :feedback="false"
          toggleMask
          :invalid="!!errors.oldPassword"
          class="w-full"
          inputClass="w-full"
        />
        <small v-if="errors.oldPassword" class="text-red-500">{{ errors.oldPassword }}</small>
      </div>

      <div class="flex flex-col gap-2">
        <label for="newPassword" class="text-surface-600 dark:text-surface-400 font-medium">
          Nouveau mot de passe
        </label>
        <Password
          id="newPassword"
          v-model="newPassword"
          toggleMask
          :invalid="!!errors.newPassword"
          class="w-full"
          inputClass="w-full"
        />
        <small v-if="errors.newPassword" class="text-red-500">{{ errors.newPassword }}</small>
      </div>

      <div class="flex flex-col gap-2">
        <label for="confirmPassword" class="text-surface-600 dark:text-surface-400 font-medium">
          Confirmer le nouveau mot de passe
        </label>
        <Password
          id="confirmPassword"
          v-model="confirmPassword"
          :feedback="false"
          toggleMask
          :invalid="!!errors.confirmPassword"
          class="w-full"
          inputClass="w-full"
        />
        <small v-if="errors.confirmPassword" class="text-red-500">{{ errors.confirmPassword }}</small>
      </div>

      <div class="pt-4">
        <Button
          type="submit"
          label="Mettre à jour le mot de passe"
          icon="pi pi-check"
          :loading="userSettingsStore.isChangingPassword"
        />
      </div>
    </form>
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import Message from 'primevue/message'
import Password from 'primevue/password'
import { ref } from 'vue'
import { useUserSettingsStore } from '../../../store/user/settings.js'

const userSettingsStore = useUserSettingsStore()

const oldPassword = ref('')
const newPassword = ref('')
const confirmPassword = ref('')
const errors = ref({})
const successMessage = ref('')
const errorMessage = ref('')

function validate() {
  errors.value = {}

  if (!oldPassword.value) {
    errors.value.oldPassword = 'Le mot de passe actuel est requis'
  }

  if (!newPassword.value) {
    errors.value.newPassword = 'Le nouveau mot de passe est requis'
  } else if (newPassword.value.length < 8) {
    errors.value.newPassword = 'Le mot de passe doit contenir au moins 8 caractères'
  }

  if (!confirmPassword.value) {
    errors.value.confirmPassword = 'Veuillez confirmer le nouveau mot de passe'
  } else if (newPassword.value !== confirmPassword.value) {
    errors.value.confirmPassword = 'Les mots de passe ne correspondent pas'
  }

  return Object.keys(errors.value).length === 0
}

async function handleSubmit() {
  successMessage.value = ''
  errorMessage.value = ''

  if (!validate()) {
    return
  }

  try {
    await userSettingsStore.changePassword({
      oldPassword: oldPassword.value,
      newPassword: newPassword.value
    })

    successMessage.value = 'Votre mot de passe a été mis à jour avec succès'
    oldPassword.value = ''
    newPassword.value = ''
    confirmPassword.value = ''
  } catch (e) {
    if (e.violationsByField?.oldPassword) {
      errors.value.oldPassword = e.violationsByField.oldPassword.map((v) => v.message).join('. ')
    } else if (e.violationsByField?.newPassword) {
      errors.value.newPassword = e.violationsByField.newPassword.map((v) => v.message).join('. ')
    } else {
      errorMessage.value = e.message || 'Une erreur est survenue'
    }
  }
}
</script>
