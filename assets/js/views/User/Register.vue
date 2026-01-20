<template>
  <div class="py-10 md:py-20 flex items-center justify-center">
    <div class="max-w-2xl w-full flex flex-col items-start gap-8 bg-surface-0 dark:bg-surface-900 p-4 md:p-12 rounded-3xl">
      <!-- Success State -->
      <template v-if="isRegistrationComplete">
        <div class="flex flex-col items-center gap-6 w-full text-center">
          <div class="w-20 h-20 rounded-full bg-green-100 dark:bg-green-900 flex items-center justify-center">
            <i class="pi pi-check text-4xl text-green-600 dark:text-green-400"></i>
          </div>
          <h1 class="text-2xl font-medium text-surface-900 dark:text-surface-0 leading-tight">
            Inscription réussie !
          </h1>
          <div class="flex flex-col gap-4 text-surface-600 dark:text-surface-300">
            <p>
              Votre compte a été créé avec succès.
            </p>
            <Message severity="info" :closable="false" class="text-left">
              <div class="flex flex-col gap-2">
                <span class="font-semibold">Confirmez votre adresse email</span>
                <span>
                  Un email de confirmation a été envoyé à <strong>{{ registeredEmail }}</strong>.
                  Veuillez cliquer sur le lien dans cet email pour activer votre compte.
                </span>
              </div>
            </Message>
            <p class="text-sm text-surface-500 dark:text-surface-400">
              Vous n'avez pas reçu l'email ? Vérifiez vos spams ou
              <a class="text-primary cursor-pointer hover:text-primary-emphasis">renvoyez l'email de confirmation</a>.
            </p>
          </div>
          <Button
            label="Aller à la page de connexion"
            class="mt-4"
            @click="goToLogin"
          />
        </div>
      </template>

      <!-- Registration Form -->
      <template v-else>
        <div class="flex flex-col items-center gap-6 w-full">
          <h1 class="text-center text-2xl font-medium text-surface-900 dark:text-surface-0 leading-tight w-full">
            Créer un compte
          </h1>
          <p class="text-center text-surface-500 dark:text-surface-400">
            Rejoignez la communauté MusicAll gratuitement
          </p>
        </div>

        <Message v-if="globalError" severity="error" :closable="false" class="w-full">
          {{ globalError }}
        </Message>

        <!-- Social Login Buttons -->
        <div class="flex flex-col gap-3 w-full">
          <a
            :href="googleAuthUrl"
            class="flex items-center justify-center gap-3 w-full p-3 rounded-lg border border-surface-300 dark:border-surface-600 bg-surface-0 dark:bg-surface-800 hover:bg-surface-100 dark:hover:bg-surface-700 transition-colors cursor-pointer no-underline"
          >
            <svg class="w-5 h-5" viewBox="0 0 24 24">
              <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
              <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
              <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
              <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
            </svg>
            <span class="text-surface-700 dark:text-surface-200 font-medium">S'inscrire avec Google</span>
          </a>
        </div>

        <!-- Separator -->
        <div class="flex items-center gap-4 w-full">
          <Divider class="flex-1" />
          <span class="text-surface-500 dark:text-surface-400 text-sm">ou</span>
          <Divider class="flex-1" />
        </div>

        <form @submit.prevent="handleSubmit" class="flex flex-col gap-6 w-full">
          <!-- Username -->
          <div class="flex flex-col gap-2">
            <label for="username" class="text-surface-900 dark:text-surface-0 font-medium">
              Nom d'utilisateur
            </label>
            <InputText
              id="username"
              v-model="form.username"
              placeholder="Votre nom d'utilisateur"
              :invalid="!!errors.username"
              class="p-3 shadow-sm dark:bg-surface-900!"
              @blur="validateUsername"
            />
            <small v-if="errors.username" class="text-red-500">{{ errors.username }}</small>
            <small v-else class="text-surface-500">
              3 à 40 caractères, lettres, chiffres, points et underscores uniquement
            </small>
          </div>

          <!-- Email -->
          <div class="flex flex-col gap-2">
            <label for="email" class="text-surface-900 dark:text-surface-0 font-medium">
              Adresse email
            </label>
            <InputText
              id="email"
              v-model="form.email"
              type="email"
              placeholder="votre@email.com"
              :invalid="!!errors.email"
              class="p-3 shadow-sm dark:bg-surface-900!"
              @blur="validateEmail"
            />
            <small v-if="errors.email" class="text-red-500">{{ errors.email }}</small>
          </div>

          <!-- Password -->
          <div class="flex flex-col gap-2">
            <label for="password" class="text-surface-900 dark:text-surface-0 font-medium">
              Mot de passe
            </label>
            <Password
              id="password"
              v-model="form.password"
              placeholder="Votre mot de passe"
              :toggleMask="true"
              :invalid="!!errors.password"
              inputClass="w-full! p-3! shadow-sm dark:bg-surface-900!"
              :promptLabel="'Choisissez un mot de passe'"
              :weakLabel="'Faible'"
              :mediumLabel="'Moyen'"
              :strongLabel="'Fort'"
              @blur="validatePassword"
            />
            <small v-if="errors.password" class="text-red-500">{{ errors.password }}</small>
            <small v-else class="text-surface-500">Minimum 6 caractères</small>
          </div>

          <!-- Confirm Password -->
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
            />
            <small v-if="errors.confirmPassword" class="text-red-500">{{ errors.confirmPassword }}</small>
          </div>

          <!-- Terms -->
          <div class="flex items-start gap-3">
            <Checkbox
              v-model="form.acceptTerms"
              inputId="acceptTerms"
              :binary="true"
              :invalid="!!errors.acceptTerms"
            />
            <label for="acceptTerms" class="text-surface-700 dark:text-surface-300 text-sm cursor-pointer">
              J'accepte les
              <RouterLink :to="{ name: 'app_terms' }" class="text-primary hover:text-primary-emphasis" target="_blank">conditions d'utilisation</RouterLink>
              et la
              <RouterLink :to="{ name: 'app_privacy' }" class="text-primary hover:text-primary-emphasis" target="_blank">politique de confidentialité</RouterLink>
            </label>
          </div>
          <small v-if="errors.acceptTerms" class="text-red-500 -mt-4">{{ errors.acceptTerms }}</small>

          <!-- Submit Button -->
          <Button
            type="submit"
            label="Créer mon compte"
            class="w-full mt-2"
            :loading="isSubmitting"
            :disabled="isSubmitting"
          />

          <!-- Login Link -->
          <div class="text-center w-full">
            <span class="text-surface-900 dark:text-surface-0 font-medium">Vous avez déjà un compte ?</span>
            <RouterLink
              :to="{ name: 'app_login', query: returnUrl ? { return_url: returnUrl } : {} }"
              class="ml-3 text-primary font-medium cursor-pointer hover:text-primary-emphasis"
            >
              Connectez-vous ici
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
import Checkbox from 'primevue/checkbox'
import Divider from 'primevue/divider'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Password from 'primevue/password'
import { trackUmamiEvent } from '@jaseeey/vue-umami-plugin'
import { computed, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import securityApi from '../../api/user/security.js'

useTitle('Créer un compte - MusicAll')

const route = useRoute()
const router = useRouter()

const returnUrl = computed(() => route.query.return_url || null)

const googleAuthUrl = computed(() => {
  if (returnUrl.value) {
    return Routing.generate('oauth_google_start', { return_url: returnUrl.value })
  }
  return Routing.generate('oauth_google_start')
})

const form = reactive({
  username: '',
  email: '',
  password: '',
  confirmPassword: '',
  acceptTerms: false
})

const errors = reactive({
  username: '',
  email: '',
  password: '',
  confirmPassword: '',
  acceptTerms: ''
})

const isSubmitting = ref(false)
const isRegistrationComplete = ref(false)
const registeredEmail = ref('')
const globalError = ref('')

function validateUsername() {
  errors.username = ''

  if (!form.username.trim()) {
    errors.username = "Veuillez saisir un nom d'utilisateur"
    return false
  }

  if (form.username.length < 3) {
    errors.username = "Le nom d'utilisateur doit au moins contenir 3 caractères"
    return false
  }

  if (form.username.length > 40) {
    errors.username = "Le nom d'utilisateur doit contenir maximum 40 caractères"
    return false
  }

  if (!/^[a-zA-Z0-9._]+$/.test(form.username)) {
    errors.username = 'Seuls les lettres, chiffres, points et underscores sont autorisés'
    return false
  }

  return true
}

function validateEmail() {
  errors.email = ''

  if (!form.email.trim()) {
    errors.email = 'Veuillez saisir un email'
    return false
  }

  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) {
    errors.email = 'Email invalide'
    return false
  }

  return true
}

function validatePassword() {
  errors.password = ''

  if (!form.password) {
    errors.password = 'Veuillez saisir un mot de passe'
    return false
  }

  if (form.password.length < 6) {
    errors.password = 'Le mot de passe doit au moins contenir 6 caractères'
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

function validateAcceptTerms() {
  errors.acceptTerms = ''

  if (!form.acceptTerms) {
    errors.acceptTerms = "Vous devez accepter les conditions d'utilisation"
    return false
  }

  return true
}

function validateForm() {
  const isUsernameValid = validateUsername()
  const isEmailValid = validateEmail()
  const isPasswordValid = validatePassword()
  const isConfirmPasswordValid = validateConfirmPassword()
  const isAcceptTermsValid = validateAcceptTerms()

  return (
    isUsernameValid &&
    isEmailValid &&
    isPasswordValid &&
    isConfirmPasswordValid &&
    isAcceptTermsValid
  )
}

async function handleSubmit() {
  globalError.value = ''

  if (!validateForm()) {
    return
  }

  isSubmitting.value = true

  try {
    await securityApi.register({
      username: form.username.trim(),
      email: form.email.trim(),
      password: form.password
    })
    trackUmamiEvent('user-register')

    registeredEmail.value = form.email.trim()
    isRegistrationComplete.value = true
  } catch (error) {
    if (error.isValidationError && error.violationsByField) {
      if (error.violationsByField.username) {
        errors.username = error.violationsByField.username[0].message
      }
      if (error.violationsByField.email) {
        errors.email = error.violationsByField.email[0].message
      }
      if (error.violationsByField.password) {
        errors.password = error.violationsByField.password[0].message
      }
    } else {
      globalError.value = error.message
    }
  } finally {
    isSubmitting.value = false
  }
}

function goToLogin() {
  router.push({ name: 'app_login' })
}
</script>
