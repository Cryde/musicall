<template>
  <div class="py-10 md:py-20 flex items-center justify-center">
    <div class="max-w-2xl w-full flex flex-col items-start gap-8 bg-surface-0 dark:bg-surface-900 p-4 md:p-12 rounded-3xl">
      <div class="flex flex-col items-center gap-2 w-full">
        <h1 class="text-center text-2xl font-medium text-surface-900 dark:text-surface-0 leading-tight w-full">
          Bon retour parmi nous !
        </h1>
        <p class="text-center text-surface-500 dark:text-surface-400">
          Connectez-vous pour accéder à votre compte
        </p>
      </div>

      <Message v-if="userSecurity.loginErrors.length > 0" severity="error" :closable="false" class="w-full">
        <span v-for="(error, index) in userSecurity.loginErrors" :key="index">{{ error }}</span>
      </Message>

      <Message v-if="oauthError" severity="error" :closable="false" class="w-full">
        {{ oauthErrorMessage }}
      </Message>

      <!-- Social Login Buttons -->
      <div class="flex flex-col gap-3 w-full">
        <a
          href="/oauth/google"
          class="flex items-center justify-center gap-3 w-full p-3 rounded-lg border border-surface-300 dark:border-surface-600 bg-surface-0 dark:bg-surface-800 hover:bg-surface-100 dark:hover:bg-surface-700 transition-colors cursor-pointer no-underline"
        >
          <svg class="w-5 h-5" viewBox="0 0 24 24">
            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
          </svg>
          <span class="text-surface-700 dark:text-surface-200 font-medium">Continuer avec Google</span>
        </a>
      </div>

      <!-- Separator -->
      <div class="flex items-center gap-4 w-full">
        <Divider class="flex-1" />
        <span class="text-surface-500 dark:text-surface-400 text-sm">ou</span>
        <Divider class="flex-1" />
      </div>

      <form @submit.prevent="sendLogin" class="flex flex-col gap-6 w-full">
        <div class="flex flex-col gap-2">
          <label for="email" class="text-surface-900 dark:text-surface-0 font-medium">
            Email ou nom d'utilisateur
          </label>
          <IconField>
            <InputIcon class="pi pi-user" />
            <InputText
              id="email"
              v-model="email"
              placeholder="Email ou nom d'utilisateur"
              :invalid="!!errors.email"
              class="p-3 shadow-sm dark:bg-surface-900! w-full"
              autocomplete="username"
              autofocus
              @blur="validateEmail"
              @input="clearFieldError('email')"
            />
          </IconField>
          <small v-if="errors.email" class="text-red-500">{{ errors.email }}</small>
        </div>

        <div class="flex flex-col gap-2">
          <label for="password" class="text-surface-900 dark:text-surface-0 font-medium">
            Mot de passe
          </label>
          <Password
            id="password"
            v-model="password"
            placeholder="Mot de passe"
            :toggleMask="true"
            :feedback="false"
            :invalid="!!errors.password"
            inputClass="w-full! dark:bg-surface-900!"
            autocomplete="current-password"
            @blur="validatePassword"
            @input="clearFieldError('password')"
          />
          <small v-if="errors.password" class="text-red-500">{{ errors.password }}</small>
        </div>

        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between w-full gap-3 sm:gap-0">
          <RouterLink
            :to="{ name: 'app_forgot_password' }"
            class="text-surface-500 dark:text-surface-400 font-medium cursor-pointer hover:text-surface-600 dark:hover:text-surface-300 text-sm"
          >
            Mot de passe oublié ?
          </RouterLink>
        </div>

        <Button
          type="submit"
          label="Me connecter"
          class="w-full"
          :loading="isLoginSubmitting"
          :disabled="isLoginSubmitting"
        />

        <div class="text-center w-full">
          <span class="text-surface-600 dark:text-surface-400">Vous n'avez pas de compte ?</span>
          <RouterLink
            :to="{ name: 'app_register' }"
            class="ml-2 text-primary font-medium cursor-pointer hover:text-primary-emphasis"
          >
            Créer un compte
          </RouterLink>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { useTitle } from '@vueuse/core'
import Button from 'primevue/button'
import Divider from 'primevue/divider'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Password from 'primevue/password'
import { trackUmamiEvent } from '@jaseeey/vue-umami-plugin'
import { computed, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useUserSecurityStore } from '../../store/user/security.js'

useTitle('Se connecter - MusicAll')

const route = useRoute()
const userSecurity = useUserSecurityStore()

const email = ref('')
const password = ref('')
const isLoginSubmitting = ref(false)

const oauthError = computed(() => route.query.oauth_error)

const oauthErrorMessages = {
  email_exists: 'Cette adresse email est déjà associée à un compte. Connectez-vous avec votre mot de passe puis liez votre compte social dans les paramètres.',
  oauth_failed: 'La connexion a échoué. Veuillez réessayer.'
}

const oauthErrorMessage = computed(() => {
  return oauthErrorMessages[oauthError.value] || 'Une erreur est survenue lors de la connexion.'
})

const errors = reactive({
  email: '',
  password: ''
})

function validateEmail() {
  errors.email = ''
  if (!email.value.trim()) {
    errors.email = "Veuillez saisir votre email ou nom d'utilisateur"
    return false
  }
  return true
}

function validatePassword() {
  errors.password = ''
  if (!password.value) {
    errors.password = 'Veuillez saisir votre mot de passe'
    return false
  }
  return true
}

function clearFieldError(field) {
  errors[field] = ''
}

function validateForm() {
  const isEmailValid = validateEmail()
  const isPasswordValid = validatePassword()
  return isEmailValid && isPasswordValid
}

async function sendLogin() {
  if (!validateForm()) {
    return
  }

  isLoginSubmitting.value = true
  trackUmamiEvent('user-login')
  await userSecurity.login(email.value, password.value)
  isLoginSubmitting.value = false
}
</script>
