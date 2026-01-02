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
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Password from 'primevue/password'
import { reactive, ref } from 'vue'
import { useUserSecurityStore } from '../../store/user/security.js'

useTitle('Se connecter - MusicAll')

const userSecurity = useUserSecurityStore()

const email = ref('')
const password = ref('')
const isLoginSubmitting = ref(false)

const errors = reactive({
  email: '',
  password: ''
})

function validateEmail() {
  errors.email = ''
  if (!email.value.trim()) {
    errors.email = 'Veuillez saisir votre email ou nom d\'utilisateur'
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
  await userSecurity.login(email.value, password.value)
  isLoginSubmitting.value = false
}
</script>
