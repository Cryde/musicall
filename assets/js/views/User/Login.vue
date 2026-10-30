<template>
  <div class="py-10 md:py-20 flex items-center justify-center">
    <div class="max-w-2xl w-full flex flex-col items-start gap-8 bg-surface-0 dark:bg-surface-900 p-4 md:p-12 rounded-3xl">
      <div class="flex flex-col items-center gap-2 w-full">
        <h1 class="text-center text-2xl font-medium text-surface-900 dark:text-surface-0 leading-tight w-full">
          {{ t('auth.login.title') }}
        </h1>
        <p class="text-center text-surface-500 dark:text-surface-400">
          {{ t('auth.login.subtitle') }}
        </p>
      </div>

      <Message v-if="userSecurity.loginErrors.length > 0" severity="error" :closable="false" class="w-full">
        <span v-for="(error, index) in userSecurity.loginErrors" :key="index">{{ error }}</span>
      </Message>

      <Message v-if="oauthError" severity="error" :closable="false" class="w-full">
        {{ oauthErrorMessage }}
      </Message>

      <!-- Why they are looking at this form rather than the page they were on. The store has set
           this since long before #1008 and nothing ever rendered it, so an expired session sent
           people here with no explanation at all. -->
      <Message
        v-if="userSecurity.authError"
        severity="warn"
        :closable="false"
        class="w-full"
        role="status"
      >
        {{ userSecurity.authError }}
      </Message>

      <SocialSignInButtons
        :return-url="returnUrl"
        :google-label="t('auth.login.google')"
        :apple-label="t('auth.login.apple')"
      />

      <!-- Separator -->
      <div class="flex items-center gap-4 w-full">
        <Divider class="flex-1" />
        <span class="text-surface-500 dark:text-surface-400 text-sm">{{ t('auth.common.or') }}</span>
        <Divider class="flex-1" />
      </div>

      <form @submit.prevent="sendLogin" class="flex flex-col gap-6 w-full">
        <div class="flex flex-col gap-2">
          <label for="email" class="text-surface-900 dark:text-surface-0 font-medium">
            {{ t('auth.common.email_or_username') }}
          </label>
          <IconField>
            <InputIcon class="pi pi-user" />
            <InputText
              id="email"
              v-model="email"
              :placeholder="t('auth.common.email_or_username')"
              :invalid="!!errors.email"
              :aria-describedby="errors.email ? 'email-error' : undefined"
              class="p-3 shadow-sm dark:bg-surface-900! w-full"
              autocomplete="username"
              autofocus
              @blur="validateEmail"
              @input="clearFieldError('email')"
            />
          </IconField>
          <small v-if="errors.email" id="email-error" role="alert" class="text-red-500">{{ errors.email }}</small>
        </div>

        <div class="flex flex-col gap-2">
          <label for="password" class="text-surface-900 dark:text-surface-0 font-medium">
            {{ t('auth.common.password') }}
          </label>
          <Password
            id="password"
            v-model="password"
            :placeholder="t('auth.common.password')"
            :toggleMask="true"
            :feedback="false"
            :invalid="!!errors.password"
            :aria-describedby="errors.password ? 'password-error' : undefined"
            :pt="{ pcInputText: { root: { 'aria-expanded': undefined, 'aria-haspopup': undefined } } }"
            inputClass="w-full! dark:bg-surface-900!"
            autocomplete="current-password"
            @blur="validatePassword"
            @input="clearFieldError('password')"
          />
          <small v-if="errors.password" id="password-error" role="alert" class="text-red-500">{{ errors.password }}</small>
        </div>

        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between w-full gap-3 sm:gap-0">
          <RouterLink
            :to="{ name: 'app_forgot_password' }"
            class="text-surface-500 dark:text-surface-400 font-medium cursor-pointer hover:text-surface-600 dark:hover:text-surface-300 text-sm"
          >
            {{ t('auth.login.forgot_password') }}
          </RouterLink>
        </div>

        <Button
          type="submit"
          :label="t('auth.login.submit')"
          class="w-full"
          :loading="isLoginSubmitting"
          :disabled="isLoginSubmitting"
        />

        <div class="text-center w-full">
          <span class="text-surface-600 dark:text-surface-400">{{ t('auth.login.no_account') }}</span>
          <RouterLink
            :to="{ name: 'app_register', query: returnUrl ? { return_url: returnUrl } : {} }"
            class="ml-2 text-primary font-medium cursor-pointer hover:text-primary-emphasis"
          >
            {{ t('auth.login.create_account') }}
          </RouterLink>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { trackUmamiEvent } from '@jaseeey/vue-umami-plugin'
import { useTitle } from '@vueuse/core'
import Button from 'primevue/button'
import Divider from 'primevue/divider'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Password from 'primevue/password'
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import SocialSignInButtons from '../../components/Auth/SocialSignInButtons.vue'
import { useUserSecurityStore } from '../../store/user/security.js'

const { t } = useI18n()

useTitle(computed(() => t('auth.login.page_title')))

const route = useRoute()
const userSecurity = useUserSecurityStore()

const email = ref('')
const password = ref('')
const isLoginSubmitting = ref(false)

const returnUrl = computed(() => route.query.return_url || null)
const oauthError = computed(() => route.query.oauth_error)

const OAUTH_ERROR_KEYS = {
  email_exists: 'auth.login.oauth_error.email_exists',
  email_not_verified: 'auth.login.oauth_error.email_not_verified',
  oauth_failed: 'auth.login.oauth_error.oauth_failed',
  account_suspended: 'auth.login.oauth_error.account_suspended'
}

const oauthErrorMessage = computed(() => {
  const key = OAUTH_ERROR_KEYS[oauthError.value]
  return key ? t(key) : t('auth.login.oauth_error.default')
})

const errors = reactive({
  email: '',
  password: ''
})

function validateEmail() {
  errors.email = ''
  if (!email.value.trim()) {
    errors.email = t('auth.common.email_or_username_required')
    return false
  }
  return true
}

function validatePassword() {
  errors.password = ''
  if (!password.value) {
    errors.password = t('auth.login.password_required')
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
  await userSecurity.login(email.value, password.value, returnUrl.value)
  isLoginSubmitting.value = false
}
</script>
