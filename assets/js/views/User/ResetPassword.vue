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
            {{ t('auth.reset_password.done_title') }}
          </h1>
          <p class="text-surface-600 dark:text-surface-300">
            {{ t('auth.reset_password.done_text') }}
          </p>
          <Button
            :label="t('common.login')"
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
            {{ t('auth.reset_password.invalid_title') }}
          </h1>
          <p class="text-surface-600 dark:text-surface-300">
            {{ t('auth.reset_password.invalid_text') }}
          </p>
          <div class="flex flex-col sm:flex-row gap-4 mt-4">
            <Button
              :label="t('auth.reset_password.request_new_link')"
              @click="goToForgotPassword"
            />
            <Button
              :label="t('auth.common.back_to_login')"
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
            {{ t('auth.reset_password.title') }}
          </h1>
          <p class="text-center text-surface-500 dark:text-surface-400">
            {{ t('auth.reset_password.subtitle') }}
          </p>
        </div>

        <Message v-if="globalError" severity="error" :closable="false" class="w-full">
          {{ globalError }}
        </Message>

        <form @submit.prevent="handleSubmit" class="flex flex-col gap-6 w-full">
          <div class="flex flex-col gap-2">
            <label for="password" class="text-surface-900 dark:text-surface-0 font-medium">
              {{ t('auth.reset_password.title') }}
            </label>
            <Password
              id="password"
              v-model="form.password"
              :placeholder="t('auth.reset_password.password_placeholder')"
              :toggleMask="true"
              :invalid="!!errors.password"
              inputClass="w-full! p-3! shadow-sm dark:bg-surface-900!"
              :promptLabel="t('auth.common.password_prompt')"
              :weakLabel="t('auth.common.password_weak')"
              :mediumLabel="t('auth.common.password_medium')"
              :strongLabel="t('auth.common.password_strong')"
              @blur="validatePassword"
              @input="clearFieldError('password')"
            />
            <small v-if="errors.password" class="text-red-500">{{ errors.password }}</small>
            <small v-else class="text-surface-500">{{ t('auth.common.password_hint') }}</small>
          </div>

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
              inputClass="w-full! p-3! shadow-sm dark:bg-surface-900!"
              @blur="validateConfirmPassword"
              @input="clearFieldError('confirmPassword')"
            />
            <small v-if="errors.confirmPassword" class="text-red-500">{{ errors.confirmPassword }}</small>
          </div>

          <Button
            type="submit"
            :label="t('auth.reset_password.submit')"
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
              {{ t('auth.common.back_to_login') }}
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
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import securityApi from '../../api/user/security.js'

const { t } = useI18n()

useTitle(computed(() => t('auth.reset_password.page_title')))

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
    errors.password = t('auth.common.password_required')
    return false
  }
  if (form.password.length < 6) {
    errors.password = t('auth.reset_password.password_too_short')
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
    if (
      error.message?.includes('token') ||
      error.message?.includes('Token') ||
      error.message?.includes('expire')
    ) {
      isTokenInvalid.value = true
    } else if (error.isValidationError && error.violationsByField?.password) {
      errors.password = error.violationsByField.password[0].message
    } else {
      globalError.value = error.message || t('auth.reset_password.generic_error')
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
