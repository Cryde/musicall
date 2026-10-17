<template>
  <div class="min-h-[80vh] flex items-center justify-center px-6 py-12">
    <div class="w-full max-w-md">
      <div v-if="isLoading" class="bg-surface-0 dark:bg-surface-900 rounded-2xl p-8 shadow-sm border border-surface-200 dark:border-surface-700">
        <div class="flex flex-col items-center gap-4">
          <Skeleton width="3rem" height="3rem" borderRadius="9999px" />
          <Skeleton width="80%" height="1.25rem" />
          <Skeleton width="50%" height="0.875rem" />
        </div>
      </div>

      <div
        v-else-if="errorMessage"
        class="bg-surface-0 dark:bg-surface-900 rounded-2xl p-8 shadow-sm border border-surface-200 dark:border-surface-700 text-center"
      >
        <i class="pi pi-exclamation-triangle text-4xl text-amber-500 mb-4"></i>
        <h1 class="text-lg font-semibold text-surface-800 dark:text-surface-100 mb-2">
          {{ errorTitle }}
        </h1>
        <p class="text-sm text-surface-500">{{ errorMessage }}</p>
      </div>

      <div
        v-else-if="metadata"
        class="bg-surface-0 dark:bg-surface-900 rounded-2xl p-8 shadow-sm border border-surface-200 dark:border-surface-700"
      >
        <div class="flex flex-col items-center text-center gap-3 mb-6">
          <div
            class="w-16 h-16 rounded-2xl bg-surface-100 dark:bg-surface-800 flex items-center justify-center"
          >
            <i :class="iconForMime(metadata.mime_type)" class="text-3xl text-surface-500"></i>
          </div>
          <h1 class="text-lg font-semibold text-surface-800 dark:text-surface-100 truncate max-w-full">
            {{ metadata.original_name }}
          </h1>
          <p class="text-sm text-surface-500">
            {{ formatBytes(metadata.size) }}
            <span v-if="metadata.mime_type" class="mx-2">{{ separator() }}</span>
            <span v-if="metadata.mime_type">{{ metadata.mime_type }}</span>
          </p>
          <p class="text-xs text-surface-400">
            {{ t('shared.public_share.expires_on', { date: formatDate(metadata.expiry_datetime) }) }}
          </p>
        </div>

        <div v-if="metadata.has_password" class="flex flex-col gap-2 mb-4">
          <label
            for="share-password"
            class="text-sm font-medium text-surface-700 dark:text-surface-200"
          >
            {{ t('shared.public_share.password_required') }}
          </label>
          <Password
            id="share-password"
            v-model="passwordValue"
            :feedback="false"
            toggle-mask
            :placeholder="t('shared.public_share.password_placeholder')"
            input-class="w-full"
            class="w-full"
            :disabled="isDownloading"
            @keydown.enter="handleDownload"
          />
          <Message
            v-if="passwordError"
            severity="error"
            :closable="false"
            class="text-sm"
          >
            {{ passwordError }}
          </Message>
        </div>

        <Button
          :label="t('shared.public_share.download')"
          icon="pi pi-download"
          class="w-full"
          :loading="isDownloading"
          :disabled="metadata.has_password && !passwordValue.trim()"
          @click="handleDownload"
        />

        <i18n-t keypath="shared.public_share.shared_via" tag="p" class="text-xs text-surface-400 text-center mt-4">
          <template #brand>
            <span class="font-medium">{{ t('shared.public_share.brand') }}</span>
          </template>
        </i18n-t>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useHead } from '@unhead/vue'
import Button from 'primevue/button'
import Message from 'primevue/message'
import Password from 'primevue/password'
import Skeleton from 'primevue/skeleton'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import publicShareApi from '../api/publicShare.js'
import { intlLocale, noValue, separator } from '../i18n/formatLocale.js'
import { downloadBlob } from '../utils/downloadBlob.js'
import { formatBytes } from '../utils/formatBytes.js'

const { t } = useI18n()
const route = useRoute()

const token = computed(() => route.params.token)

const metadata = ref(null)
const isLoading = ref(true)
const errorTitle = ref('')
const errorMessage = ref('')

const passwordValue = ref('')
const passwordError = ref(null)
const isDownloading = ref(false)

useHead({ title: computed(() => t('shared.public_share.page_title', { separator: '|' })) })

onMounted(() => {
  loadMetadata()
})

async function loadMetadata() {
  isLoading.value = true
  errorTitle.value = ''
  errorMessage.value = ''
  try {
    metadata.value = await publicShareApi.getMetadata(token.value)
  } catch (e) {
    setError(e)
  } finally {
    isLoading.value = false
  }
}

async function handleDownload() {
  if (!metadata.value) return
  passwordError.value = null
  isDownloading.value = true

  const url = publicShareApi.buildDownloadUrl(token.value)

  if (metadata.value.has_password) {
    // Pre-flight HEAD-like check via fetch to surface an inline 401 instead of
    // navigating to a raw error page when the password is wrong. The password
    // travels in a header (never the URL) so it can't leak via logs/history.
    try {
      const probe = await fetch(url, {
        method: 'GET',
        headers: { 'X-Share-Password': passwordValue.value }
      })
      if (probe.status === 401) {
        passwordError.value = t('shared.public_share.wrong_password')
        isDownloading.value = false
        return
      }
      if (probe.status === 410) {
        setError({ status: 410 })
        isDownloading.value = false
        return
      }
      if (!probe.ok) {
        passwordError.value = t('shared.public_share.download_failed')
        isDownloading.value = false
        return
      }
      const blob = await probe.blob()
      downloadBlob(blob, metadata.value.original_name)
    } catch {
      passwordError.value = t('shared.public_share.download_failed')
    } finally {
      isDownloading.value = false
    }
    return
  }

  // No password — let the browser handle the download natively.
  window.location.href = url
  isDownloading.value = false
}

function setError(e) {
  if (e?.status === 404) {
    errorTitle.value = t('shared.public_share.not_found_title')
    errorMessage.value = t('shared.public_share.not_found_text')
  } else if (e?.status === 410) {
    errorTitle.value = t('shared.public_share.expired_title')
    errorMessage.value = t('shared.public_share.expired_text')
  } else {
    errorTitle.value = t('shared.public_share.error_title')
    errorMessage.value = e?.message || t('shared.public_share.generic_error')
  }
}

function iconForMime(mime) {
  if (!mime) return 'pi pi-file'
  if (mime.startsWith('audio/')) return 'pi pi-volume-up'
  if (mime.startsWith('image/')) return 'pi pi-image'
  if (mime.startsWith('video/')) return 'pi pi-video'
  if (mime === 'application/pdf') return 'pi pi-file-pdf'
  return 'pi pi-file'
}

function formatDate(iso) {
  if (!iso) return noValue()
  return new Date(iso).toLocaleDateString(intlLocale(), {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}
</script>
