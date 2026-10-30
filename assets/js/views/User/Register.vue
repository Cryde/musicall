<template>
  <div class="py-10 md:py-20 flex items-center justify-center">
    <div class="max-w-2xl w-full flex flex-col items-start gap-8 bg-surface-0 dark:bg-surface-900 p-4 md:p-12 rounded-3xl">
      <!-- Email Verified State -->
      <template v-if="isEmailVerified">
        <div class="flex flex-col items-center gap-6 w-full text-center">
          <div class="w-20 h-20 rounded-full bg-green-100 dark:bg-green-900 flex items-center justify-center">
            <i class="pi pi-check text-4xl text-green-600 dark:text-green-400"></i>
          </div>
          <h1 class="text-2xl font-medium text-surface-900 dark:text-surface-0 leading-tight">
            {{ t('auth.otp.verified_title') }}
          </h1>
          <p class="text-surface-600 dark:text-surface-300">
            {{ t('auth.otp.verified_text') }}
          </p>
          <Button
            :label="t('common.login')"
            class="mt-4"
            @click="goToLogin"
          />
        </div>
      </template>

      <!-- OTP Verification State -->
      <template v-else-if="isRegistrationComplete">
        <div class="flex flex-col items-center gap-6 w-full text-center">
          <div class="w-20 h-20 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center">
            <i class="pi pi-envelope text-4xl text-blue-600 dark:text-blue-400"></i>
          </div>
          <h1 class="text-2xl font-medium text-surface-900 dark:text-surface-0 leading-tight">
            {{ t('auth.otp.check_email_title') }}
          </h1>
          <i18n-t keypath="auth.otp.code_sent" tag="p" class="text-surface-600 dark:text-surface-300">
            <template #email><strong>{{ registeredEmail }}</strong></template>
          </i18n-t>

          <Message v-if="otpError" severity="error" :closable="false" class="w-full text-left">
            {{ otpError }}
          </Message>

          <OtpInput
            ref="otpInputRef"
            :has-error="!!otpError"
            :disabled="isVerifying"
            @complete="handleOtpComplete"
          />

          <p v-if="expiryCountdown > 0" class="text-sm text-surface-500 dark:text-surface-400">
            {{ t('auth.otp.expires_in', { time: formattedExpiry }) }}
          </p>

          <div class="flex flex-col items-center gap-2">
            <Button
              v-if="resendCountdown <= 0"
              :label="t('auth.otp.resend')"
              severity="secondary"
              text
              :loading="isResending"
              :disabled="isResending"
              @click="handleResendCode"
            />
            <p v-else class="text-sm text-surface-500 dark:text-surface-400">
              {{ t('auth.otp.resend_in', { seconds: resendCountdown }) }}
            </p>
          </div>
        </div>
      </template>

      <!-- Registration Form -->
      <template v-else>
        <div class="flex flex-col items-center gap-6 w-full">
          <h1 class="text-center text-2xl font-medium text-surface-900 dark:text-surface-0 leading-tight w-full">
            {{ t('auth.register.title') }}
          </h1>
          <p class="text-center text-surface-500 dark:text-surface-400">
            {{ t('auth.register.subtitle') }}
          </p>
        </div>

        <Message v-if="invitationBandSpaceName" severity="info" :closable="false" class="w-full">
          <i18n-t keypath="auth.register.invitation">
            <template #band_space><strong>{{ invitationBandSpaceName }}</strong></template>
          </i18n-t>
        </Message>

        <Message v-if="globalError" severity="error" :closable="false" class="w-full">
          {{ globalError }}
        </Message>

        <SocialSignInButtons
          :return-url="returnUrl"
          :google-label="t('auth.register.google')"
          :apple-label="t('auth.register.apple')"
        />

        <!-- Separator -->
        <div class="flex items-center gap-4 w-full">
          <Divider class="flex-1" />
          <span class="text-surface-500 dark:text-surface-400 text-sm">{{ t('auth.common.or') }}</span>
          <Divider class="flex-1" />
        </div>

        <form @submit.prevent="handleSubmit" class="flex flex-col gap-6 w-full">
          <!-- Username -->
          <div class="flex flex-col gap-2">
            <label for="username" class="text-surface-900 dark:text-surface-0 font-medium">
              {{ t('auth.register.username') }}
            </label>
            <InputText
              id="username"
              v-model="form.username"
              :placeholder="t('auth.register.username_placeholder')"
              :invalid="!!errors.username"
              :aria-describedby="errors.username ? 'username-error' : undefined"
              class="p-3 shadow-sm dark:bg-surface-900!"
              @blur="validateUsername"
            />
            <small v-if="errors.username" id="username-error" role="alert" class="text-red-500">{{ errors.username }}</small>
            <small v-else class="text-surface-500 dark:text-surface-400">
              {{ t('auth.register.username_hint') }}
            </small>
          </div>

          <!-- Email -->
          <div class="flex flex-col gap-2">
            <label for="email" class="text-surface-900 dark:text-surface-0 font-medium">
              {{ t('auth.register.email') }}
            </label>
            <InputText
              id="email"
              v-model="form.email"
              type="email"
              :placeholder="t('auth.register.email_placeholder', { at: '@' })"
              :invalid="!!errors.email"
              :aria-describedby="errors.email ? 'register-email-error' : undefined"
              class="p-3 shadow-sm dark:bg-surface-900!"
              @blur="validateEmail"
            />
            <small v-if="errors.email" id="register-email-error" role="alert" class="text-red-500">{{ errors.email }}</small>
          </div>

          <!-- Password -->
          <div class="flex flex-col gap-2">
            <label for="password" class="text-surface-900 dark:text-surface-0 font-medium">
              {{ t('auth.common.password') }}
            </label>
            <Password
              id="password"
              v-model="form.password"
              :placeholder="t('auth.register.password_placeholder')"
              :toggleMask="true"
              :invalid="!!errors.password"
              :aria-describedby="errors.password ? 'register-password-error' : undefined"
              :pt="{ pcInputText: { root: { 'aria-expanded': undefined, 'aria-haspopup': undefined } } }"
              inputClass="w-full! p-3! shadow-sm dark:bg-surface-900!"
              :promptLabel="t('auth.common.password_prompt')"
              :weakLabel="t('auth.common.password_weak')"
              :mediumLabel="t('auth.common.password_medium')"
              :strongLabel="t('auth.common.password_strong')"
              @blur="validatePassword"
            />
            <small v-if="errors.password" id="register-password-error" role="alert" class="text-red-500">{{ errors.password }}</small>
            <small v-else class="text-surface-500 dark:text-surface-400">{{ t('auth.common.password_hint') }}</small>
          </div>

          <!-- Confirm Password -->
          <div class="flex flex-col gap-2">
            <label for="confirmPassword" class="text-surface-900 dark:text-surface-0 font-medium">
              {{ t('auth.common.confirm_password') }}
            </label>
            <Password
              id="confirmPassword"
              v-model="form.confirmPassword"
              :placeholder="t('auth.common.confirm_password_placeholder')"
              :toggleMask="true"
              :feedback="false"
              :invalid="!!errors.confirmPassword"
              :aria-describedby="errors.confirmPassword ? 'confirmPassword-error' : undefined"
              :pt="{ pcInputText: { root: { 'aria-expanded': undefined, 'aria-haspopup': undefined } } }"
              inputClass="w-full! p-3! shadow-sm dark:bg-surface-900!"
              @blur="validateConfirmPassword"
            />
            <small v-if="errors.confirmPassword" id="confirmPassword-error" role="alert" class="text-red-500">{{ errors.confirmPassword }}</small>
          </div>

          <!-- Terms -->
          <div class="flex items-start gap-3">
            <Checkbox
              v-model="form.acceptTerms"
              inputId="acceptTerms"
              :binary="true"
              :invalid="!!errors.acceptTerms"
              :aria-describedby="errors.acceptTerms ? 'acceptTerms-error' : undefined"
            />
            <i18n-t
              keypath="auth.register.accept_terms"
              tag="label"
              for="acceptTerms"
              class="text-surface-700 dark:text-surface-300 text-sm cursor-pointer"
            >
              <template #terms>
                <RouterLink :to="{ name: 'app_terms' }" class="text-primary hover:text-primary-emphasis" target="_blank">{{ t('auth.register.terms') }}</RouterLink>
              </template>
              <template #privacy>
                <RouterLink :to="{ name: 'app_privacy' }" class="text-primary hover:text-primary-emphasis" target="_blank">{{ t('auth.register.privacy') }}</RouterLink>
              </template>
            </i18n-t>
          </div>
          <small v-if="errors.acceptTerms" id="acceptTerms-error" role="alert" class="text-red-500 -mt-4">{{ errors.acceptTerms }}</small>

          <!-- Submit Button -->
          <Button
            type="submit"
            :label="t('auth.register.submit')"
            class="w-full mt-2"
            :loading="isSubmitting"
            :disabled="isSubmitting"
          />

          <!-- Login Link -->
          <div class="text-center w-full">
            <span class="text-surface-900 dark:text-surface-0 font-medium">{{ t('auth.register.has_account') }}</span>
            <RouterLink
              :to="{ name: 'app_login', query: returnUrl ? { return_url: returnUrl } : {} }"
              class="ml-3 text-primary font-medium cursor-pointer hover:text-primary-emphasis"
            >
              {{ t('auth.register.login_here') }}
            </RouterLink>
          </div>
        </form>
      </template>
    </div>
  </div>
</template>

<script setup>
import { trackUmamiEvent } from '@jaseeey/vue-umami-plugin'
import { useTitle } from '@vueuse/core'
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import Divider from 'primevue/divider'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Password from 'primevue/password'
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import bandSpaceSettingsApi from '../../api/bandSpace/band-space-settings.js'
import emailVerificationApi from '../../api/user/emailVerification.js'
import securityApi from '../../api/user/security.js'
import OtpInput from '../../components/Auth/OtpInput.vue'
import SocialSignInButtons from '../../components/Auth/SocialSignInButtons.vue'

const { t } = useI18n()

useTitle(computed(() => t('auth.register.page_title')))

const route = useRoute()
const router = useRouter()

const returnUrl = computed(() => route.query.return_url || null)
const invitationToken = computed(() => route.query.invitation_token || null)
const invitationBandSpaceName = ref(null)

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
const isEmailVerified = ref(false)
const registeredEmail = ref('')
const globalError = ref('')

// OTP state
const otpInputRef = ref(null)
const otpError = ref('')
const isVerifying = ref(false)
const isResending = ref(false)
const resendCountdown = ref(60)
const expiryCountdown = ref(15 * 60)

let resendTimer = null
let expiryTimer = null

const formattedExpiry = computed(() => {
  const minutes = Math.floor(expiryCountdown.value / 60)
  const seconds = expiryCountdown.value % 60
  return `${minutes}:${seconds.toString().padStart(2, '0')}`
})

function startCountdowns() {
  resendCountdown.value = 60
  expiryCountdown.value = 15 * 60

  resendTimer = setInterval(() => {
    if (resendCountdown.value > 0) {
      resendCountdown.value--
    }
  }, 1000)

  expiryTimer = setInterval(() => {
    if (expiryCountdown.value > 0) {
      expiryCountdown.value--
    } else {
      clearInterval(expiryTimer)
    }
  }, 1000)
}

function clearCountdowns() {
  if (resendTimer) clearInterval(resendTimer)
  if (expiryTimer) clearInterval(expiryTimer)
}

onMounted(async () => {
  if (invitationToken.value) {
    try {
      const info = await bandSpaceSettingsApi.getInvitationInfo(invitationToken.value)
      form.email = info.email
      invitationBandSpaceName.value = info.band_space_name
    } catch {
      // Token invalid or expired — user can still register normally
    }
  }
})

onBeforeUnmount(() => {
  clearCountdowns()
})

const OTP_ERROR_KEYS = {
  invalid_code: 'auth.otp.error.invalid_code',
  max_attempts_reached: 'auth.otp.error.max_attempts_reached',
  code_expired: 'auth.otp.error.code_expired',
  no_code_found: 'auth.otp.error.no_code_found'
}

async function handleOtpComplete(code) {
  otpError.value = ''
  isVerifying.value = true

  try {
    await emailVerificationApi.checkCode(registeredEmail.value, code)
    isEmailVerified.value = true
    clearCountdowns()
  } catch (error) {
    const errorKey = OTP_ERROR_KEYS[error.message]
    otpError.value = errorKey ? t(errorKey) : t('auth.common.generic_error')
    otpInputRef.value?.clear()
  } finally {
    isVerifying.value = false
  }
}

async function handleResendCode() {
  otpError.value = ''
  isResending.value = true

  try {
    await emailVerificationApi.sendCode(registeredEmail.value)
    resendCountdown.value = 60
    expiryCountdown.value = 15 * 60
    otpInputRef.value?.clear()
  } catch (error) {
    otpError.value = error.message || t('auth.otp.resend_failed')
  } finally {
    isResending.value = false
  }
}

function validateUsername() {
  errors.username = ''

  if (!form.username.trim()) {
    errors.username = t('auth.register.error.username_required')
    return false
  }

  if (form.username.length < 3) {
    errors.username = t('auth.register.error.username_too_short')
    return false
  }

  if (form.username.length > 40) {
    errors.username = t('auth.register.error.username_too_long')
    return false
  }

  if (!/^[a-zA-Z0-9._]+$/.test(form.username)) {
    errors.username = t('auth.register.error.username_invalid')
    return false
  }

  return true
}

function validateEmail() {
  errors.email = ''

  if (!form.email.trim()) {
    errors.email = t('auth.register.error.email_required')
    return false
  }

  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) {
    errors.email = t('auth.register.error.email_invalid')
    return false
  }

  return true
}

function validatePassword() {
  errors.password = ''

  if (!form.password) {
    errors.password = t('auth.common.password_required')
    return false
  }

  if (form.password.length < 6) {
    errors.password = t('auth.register.error.password_too_short')
    return false
  }

  return true
}

function validateConfirmPassword() {
  errors.confirmPassword = ''

  if (!form.confirmPassword) {
    errors.confirmPassword = t('auth.common.confirm_password_required')
    return false
  }

  if (form.password !== form.confirmPassword) {
    errors.confirmPassword = t('auth.common.passwords_mismatch')
    return false
  }

  return true
}

function validateAcceptTerms() {
  errors.acceptTerms = ''

  if (!form.acceptTerms) {
    errors.acceptTerms = t('auth.register.error.terms_required')
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
    startCountdowns()
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
  // Carries the destination on, or an invited visitor who registered here still loses the
  // invitation at the last hop. The link at the top of this page already does the same.
  router.push({
    name: 'app_login',
    query: returnUrl.value ? { return_url: returnUrl.value } : {}
  })
}
</script>
