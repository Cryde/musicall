<template>
  <div class="py-10 md:py-20 flex items-center justify-center">
    <div class="max-w-2xl w-full flex flex-col items-start gap-8 bg-surface-0 dark:bg-surface-900 p-4 md:p-12 rounded-3xl">
      <!-- Success State -->
      <template v-if="isResetComplete">
        <div class="flex flex-col items-center gap-6 w-full text-center">
          <div class="w-20 h-20 rounded-full bg-green-100 dark:bg-green-900 flex items-center justify-center">
            <i class="pi pi-check text-4xl text-green-600 dark:text-green-400"></i>
          </div>
          <h1 class="text-2xl font-medium text-surface-900 dark:text-surface-0 leading-tight">
            Mot de passe modifié !
          </h1>
          <p class="text-surface-600 dark:text-surface-300">
            Votre mot de passe a été réinitialisé avec succès.
            Vous pouvez maintenant vous connecter avec votre nouveau mot de passe.
          </p>
          <Button
            label="Se connecter"
            class="mt-4"
            @click="goToLogin"
          />
        </div>
      </template>

      <!-- Invalid Token State -->
      <template v-else-if="isTokenInvalid">
        <div class="flex flex-col items-center gap-6 w-full text-center">
          <div class="w-20 h-20 rounded-full bg-red-100 dark:bg-red-900 flex items-center justify-center">
            <i class="pi pi-times text-4xl text-red-600 dark:text-red-400"></i>
          </div>
          <h1 class="text-2xl font-medium text-surface-900 dark:text-surface-0 leading-tight">
            Lien invalide ou expiré
          </h1>
          <p class="text-surface-600 dark:text-surface-300">
            Ce lien de réinitialisation n'est plus valide. Il a peut-être expiré ou a déjà été utilisé.
          </p>
          <div class="flex flex-col sm:flex-row gap-4 mt-4">
            <Button
              label="Demander un nouveau lien"
              @click="goToForgotPassword"
            />
            <Button
              label="Retour à la connexion"
              severity="secondary"
              outlined
              @click="goToLogin"
            />
          </div>
        </div>
      </template>

      <!-- Reset Form -->
      <template v-else>
        <div class="flex flex-col items-center gap-2 w-full">
          <div class="w-16 h-16 rounded-full bg-primary-100 dark:bg-primary-900 flex items-center justify-center mb-2">
            <i class="pi pi-key text-3xl text-primary-600 dark:text-primary-400"></i>
          </div>
          <h1 class="text-center text-2xl font-medium text-surface-900 dark:text-surface-0 leading-tight w-full">
            Nouveau mot de passe
          </h1>
          <p class="text-center text-surface-500 dark:text-surface-400">
            Choisissez un nouveau mot de passe pour votre compte.
          </p>
        </div>

        <Message v-if="globalError" severity="error" :closable="false" class="w-full">
          {{ globalError }}
        </Message>

        <form @submit.prevent="handleSubmit" class="flex flex-col gap-6 w-full">
          <div class="flex flex-col gap-2">
            <label for="password" class="text-surface-900 dark:text-surface-0 font-medium">
              Nouveau mot de passe
            </label>
            <Password
              id="password"
              v-model="form.password"
              placeholder="Votre nouveau mot de passe"
              :toggleMask="true"
              :invalid="!!errors.password"
              inputClass="w-full! p-3! shadow-sm dark:bg-surface-900!"
              :promptLabel="'Choisissez un mot de passe'"
              :weakLabel="'Faible'"
              :mediumLabel="'Moyen'"
              :strongLabel="'Fort'"
              @blur="validatePassword"
              @input="clearFieldError('password')"
            />
            <small v-if="errors.password" class="text-red-500">{{ errors.password }}</small>
            <small v-else class="text-surface-500">Minimum 6 caractères</small>
          </div>

          <div class="flex flex-col gap-2">
            <label for="confirmPassword" class="text-surface-900 dark:text-surface-0 font-medium">
              Confirmer le mot de passe
            </label>
            <Password
              id="confirmPassword"
              v-model="form.confirmPassword"
              placeholder="Confirmez votre mot de passe"
              :toggleMask="true"
              :feedback="false"
              :invalid="!!errors.confirmPassword"
              inputClass="w-full! p-3! shadow-sm dark:bg-surface-900!"
              @blur="validateConfirmPassword"
              @input="clearFieldError('confirmPassword')"
            />
            <small v-if="errors.confirmPassword" class="text-red-500">{{ errors.confirmPassword }}</small>
          </div>

          <Button
            type="submit"
            label="Réinitialiser le mot de passe"
            class="w-full"
            :loading="isSubmitting"
            :disabled="isSubmitting"
          />

          <div class="text-center w-full">
            <RouterLink
              :to="{ name: 'app_login' }"
              class="text-primary font-medium cursor-pointer hover:text-primary-emphasis"
            >
              <i class="pi pi-arrow-left mr-2"></i>
              Retour à la connexion
            </RouterLink>
          </div>
        </form>
      </template>
    </div>
  </div>
</template>

<script setup>
import { useTitle } from '@vueuse/core'
import Button from 'primevue/button'
import Message from 'primevue/message'
import Password from 'primevue/password'
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import securityApi from '../../api/user/security.js'

useTitle('Réinitialiser le mot de passe - MusicAll')

const route = useRoute()
const router = useRouter()

const token = route.params.token

const form = reactive({
  password: '',
  confirmPassword: ''
})

const errors = reactive({
  password: '',
  confirmPassword: ''
})

const isSubmitting = ref(false)
const isResetComplete = ref(false)
const isTokenInvalid = ref(false)
const globalError = ref('')

function validatePassword() {
  errors.password = ''
  if (!form.password) {
    errors.password = 'Veuillez saisir un mot de passe'
    return false
  }
  if (form.password.length < 6) {
    errors.password = 'Le mot de passe doit contenir au moins 6 caractères'
    return false
  }
  return true
}

function validateConfirmPassword() {
  errors.confirmPassword = ''
  if (!form.confirmPassword) {
    errors.confirmPassword = 'Veuillez confirmer votre mot de passe'
    return false
  }
  if (form.password !== form.confirmPassword) {
    errors.confirmPassword = 'Les mots de passe ne correspondent pas'
    return false
  }
  return true
}

function clearFieldError(field) {
  errors[field] = ''
  globalError.value = ''
}

function validateForm() {
  const isPasswordValid = validatePassword()
  const isConfirmPasswordValid = validateConfirmPassword()
  return isPasswordValid && isConfirmPasswordValid
}

async function handleSubmit() {
  if (!validateForm()) {
    return
  }

  isSubmitting.value = true
  globalError.value = ''

  try {
    await securityApi.resetPassword(token, form.password)
    isResetComplete.value = true
  } catch (error) {
    if (error.message?.includes('token') || error.message?.includes('Token') || error.message?.includes('expire')) {
      isTokenInvalid.value = true
    } else if (error.isValidationError && error.violationsByField?.password) {
      errors.password = error.violationsByField.password[0].message
    } else {
      globalError.value = error.message || 'Une erreur est survenue. Veuillez réessayer.'
    }
  } finally {
    isSubmitting.value = false
  }
}

function goToLogin() {
  router.push({ name: 'app_login' })
}

function goToForgotPassword() {
  router.push({ name: 'app_forgot_password' })
}
</script>
