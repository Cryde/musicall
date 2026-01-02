<template>
  <div class="py-10 md:py-20 flex items-center justify-center">
    <div class="max-w-2xl w-full flex flex-col items-start gap-8 bg-surface-0 dark:bg-surface-900 p-4 md:p-12 rounded-3xl">
      <!-- Success State -->
      <template v-if="isRequestSent">
        <div class="flex flex-col items-center gap-6 w-full text-center">
          <div class="w-20 h-20 rounded-full bg-green-100 dark:bg-green-900 flex items-center justify-center">
            <i class="pi pi-check text-4xl text-green-600 dark:text-green-400"></i>
          </div>
          <h1 class="text-2xl font-medium text-surface-900 dark:text-surface-0 leading-tight">
            Email envoyé !
          </h1>
          <div class="flex flex-col gap-4 text-surface-600 dark:text-surface-300">
            <p>
              Si un compte existe avec cette adresse, vous recevrez un email avec les instructions pour réinitialiser votre mot de passe.
            </p>
            <Message severity="info" :closable="false" class="text-left">
              <div class="flex flex-col gap-2">
                <span class="font-semibold">Vérifiez votre boîte mail</span>
                <span>
                  Le lien de réinitialisation est valable pendant 24 heures.
                  Si vous ne recevez pas l'email, vérifiez vos spams.
                </span>
              </div>
            </Message>
          </div>
          <Button
            label="Retour à la connexion"
            class="mt-4"
            @click="goToLogin"
          />
        </div>
      </template>

      <!-- Request Form -->
      <template v-else>
        <div class="flex flex-col items-center gap-2 w-full">
          <div class="w-16 h-16 rounded-full bg-primary-100 dark:bg-primary-900 flex items-center justify-center mb-2">
            <i class="pi pi-lock text-3xl text-primary-600 dark:text-primary-400"></i>
          </div>
          <h1 class="text-center text-2xl font-medium text-surface-900 dark:text-surface-0 leading-tight w-full">
            Mot de passe oublié ?
          </h1>
          <p class="text-center text-surface-500 dark:text-surface-400">
            Entrez votre email ou nom d'utilisateur et nous vous enverrons un lien pour réinitialiser votre mot de passe.
          </p>
        </div>

        <Message v-if="globalError" severity="error" :closable="false" class="w-full">
          {{ globalError }}
        </Message>

        <form @submit.prevent="handleSubmit" class="flex flex-col gap-6 w-full">
          <div class="flex flex-col gap-2">
            <label for="login" class="text-surface-900 dark:text-surface-0 font-medium">
              Email ou nom d'utilisateur
            </label>
            <IconField>
              <InputIcon class="pi pi-user" />
              <InputText
                id="login"
                v-model="login"
                placeholder="Email ou nom d'utilisateur"
                :invalid="!!errors.login"
                class="p-3 shadow-sm dark:bg-surface-900! w-full"
                autofocus
                @blur="validateLogin"
                @input="clearError"
              />
            </IconField>
            <small v-if="errors.login" class="text-red-500">{{ errors.login }}</small>
          </div>

          <Button
            type="submit"
            label="Envoyer le lien"
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
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import securityApi from '../../api/user/security.js'

useTitle('Mot de passe oublié - MusicAll')

const router = useRouter()

const login = ref('')
const isSubmitting = ref(false)
const isRequestSent = ref(false)
const globalError = ref('')

const errors = reactive({
  login: ''
})

function validateLogin() {
  errors.login = ''
  if (!login.value.trim()) {
    errors.login = 'Veuillez saisir votre email ou nom d\'utilisateur'
    return false
  }
  return true
}

function clearError() {
  errors.login = ''
  globalError.value = ''
}

async function handleSubmit() {
  if (!validateLogin()) {
    return
  }

  isSubmitting.value = true
  globalError.value = ''

  try {
    await securityApi.requestResetPassword(login.value.trim())
    isRequestSent.value = true
  } catch (error) {
    // Always show success to prevent email enumeration
    // The API should also not reveal if the email exists
    isRequestSent.value = true
  } finally {
    isSubmitting.value = false
  }
}

function goToLogin() {
  router.push({ name: 'app_login' })
}
</script>
