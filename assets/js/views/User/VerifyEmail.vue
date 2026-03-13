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
            Email vérifié !
          </h1>
          <p class="text-surface-600 dark:text-surface-300">
            Votre adresse email a été vérifiée avec succès. Vous pouvez maintenant vous connecter.
          </p>
          <Button
            label="Se connecter"
            class="mt-4"
            @click="goToLogin"
          />
        </div>
      </template>

      <!-- OTP Verification State -->
      <template v-else>
        <div class="flex flex-col items-center gap-6 w-full text-center">
          <div class="w-20 h-20 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center">
            <i class="pi pi-envelope text-4xl text-blue-600 dark:text-blue-400"></i>
          </div>
          <h1 class="text-2xl font-medium text-surface-900 dark:text-surface-0 leading-tight">
            Vérifiez votre email
          </h1>
          <p class="text-surface-600 dark:text-surface-300">
            Un code à 6 chiffres a été envoyé à <strong>{{ email }}</strong>.
            Saisissez-le ci-dessous pour vérifier votre adresse email.
          </p>

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
            Le code expire dans {{ formattedExpiry }}
          </p>

          <div class="flex flex-col items-center gap-2">
            <Button
              v-if="resendCountdown <= 0"
              label="Renvoyer le code"
              severity="secondary"
              text
              :loading="isResending"
              :disabled="isResending"
              @click="handleResendCode"
            />
            <p v-else class="text-sm text-surface-500 dark:text-surface-400">
              Renvoyer le code dans {{ resendCountdown }}s
            </p>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>

<script setup>
import { useTitle } from '@vueuse/core'
import Button from 'primevue/button'
import Message from 'primevue/message'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import emailVerificationApi from '../../api/user/emailVerification.js'
import OtpInput from '../../components/Auth/OtpInput.vue'

useTitle('Vérifier votre email - MusicAll')

const route = useRoute()
const router = useRouter()

const email = computed(() => route.query.email || '')

const isEmailVerified = ref(false)
const otpInputRef = ref(null)
const otpError = ref('')
const isVerifying = ref(false)
const isResending = ref(false)
const resendCountdown = ref(0)
const expiryCountdown = ref(0)

let resendTimer = null
let expiryTimer = null

const formattedExpiry = computed(() => {
  const minutes = Math.floor(expiryCountdown.value / 60)
  const seconds = expiryCountdown.value % 60
  return `${minutes}:${seconds.toString().padStart(2, '0')}`
})

const OTP_ERROR_MESSAGES = {
  invalid_code: 'Le code saisi est incorrect.',
  max_attempts_reached: 'Nombre maximum de tentatives atteint. Veuillez renvoyer un nouveau code.',
  code_expired: 'Le code a expiré. Veuillez en demander un nouveau.',
  no_code_found: 'Aucun code en attente. Veuillez en demander un nouveau.'
}

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

onBeforeUnmount(() => {
  clearCountdowns()
})

onMounted(async () => {
  if (!email.value) {
    await router.replace({ name: 'app_login' })
    return
  }

  await sendInitialCode()
})

async function sendInitialCode() {
  try {
    await emailVerificationApi.sendCode(email.value)
    startCountdowns()
  } catch (error) {
    // Cooldown error means a code was recently sent — still show OTP input
    if (error.status === 429) {
      startCountdowns()
      return
    }
    // Silent handling for other errors (unknown email, already verified)
    startCountdowns()
  }
}

async function handleOtpComplete(code) {
  otpError.value = ''
  isVerifying.value = true

  try {
    await emailVerificationApi.checkCode(email.value, code)
    isEmailVerified.value = true
    clearCountdowns()
  } catch (error) {
    otpError.value = OTP_ERROR_MESSAGES[error.message] || 'Une erreur est survenue.'
    otpInputRef.value?.clear()
  } finally {
    isVerifying.value = false
  }
}

async function handleResendCode() {
  otpError.value = ''
  isResending.value = true

  try {
    await emailVerificationApi.sendCode(email.value)
    clearCountdowns()
    startCountdowns()
    otpInputRef.value?.clear()
  } catch (error) {
    otpError.value = error.message || 'Impossible de renvoyer le code.'
  } finally {
    isResending.value = false
  }
}

function goToLogin() {
  router.push({ name: 'app_login' })
}
</script>
