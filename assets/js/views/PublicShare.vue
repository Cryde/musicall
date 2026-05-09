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
            <span v-if="metadata.mime_type" class="mx-2">·</span>
            <span v-if="metadata.mime_type">{{ metadata.mime_type }}</span>
          </p>
          <p class="text-xs text-surface-400">
            Expire le {{ formatDate(metadata.expiry_datetime) }}
          </p>
        </div>

        <div v-if="metadata.has_password" class="flex flex-col gap-2 mb-4">
          <label
            for="share-password"
            class="text-sm font-medium text-surface-700 dark:text-surface-200"
          >
            Mot de passe requis
          </label>
          <Password
            id="share-password"
            v-model="passwordValue"
            :feedback="false"
            toggle-mask
            placeholder="Saisissez le mot de passe"
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
          label="Télécharger"
          icon="pi pi-download"
          class="w-full"
          :loading="isDownloading"
          :disabled="metadata.has_password && !passwordValue.trim()"
          @click="handleDownload"
        />

        <p class="text-xs text-surface-400 text-center mt-4">
          Lien partagé via <span class="font-medium">MusicAll</span>
        </p>
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
import { useRoute } from 'vue-router'
import publicShareApi from '../api/publicShare.js'
import { formatBytes } from '../utils/formatBytes.js'

const route = useRoute()

const token = computed(() => route.params.token)

const metadata = ref(null)
const isLoading = ref(true)
const errorTitle = ref('')
const errorMessage = ref('')

const passwordValue = ref('')
const passwordError = ref(null)
const isDownloading = ref(false)

useHead({ title: 'Partage de fichier | MusicAll' })

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

  const url = publicShareApi.buildDownloadUrl(
    token.value,
    metadata.value.has_password ? passwordValue.value : null
  )

  if (metadata.value.has_password) {
    // Pre-flight HEAD-like check via fetch to surface an inline 401 instead of
    // navigating to a raw error page when the password is wrong.
    try {
      const probe = await fetch(url, { method: 'GET' })
      if (probe.status === 401) {
        passwordError.value = 'Mot de passe incorrect.'
        isDownloading.value = false
        return
      }
      if (probe.status === 410) {
        setError({ status: 410 })
        isDownloading.value = false
        return
      }
      if (!probe.ok) {
        passwordError.value = 'Téléchargement impossible.'
        isDownloading.value = false
        return
      }
      const blob = await probe.blob()
      triggerDownload(blob, metadata.value.original_name)
    } catch {
      passwordError.value = 'Téléchargement impossible.'
    } finally {
      isDownloading.value = false
    }
    return
  }

  // No password — let the browser handle the download natively.
  window.location.href = url
  isDownloading.value = false
}

function triggerDownload(blob, filename) {
  const objectUrl = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = objectUrl
  a.download = filename
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
  URL.revokeObjectURL(objectUrl)
}

function setError(e) {
  if (e?.status === 404) {
    errorTitle.value = 'Lien introuvable'
    errorMessage.value = "Ce lien de partage n'existe pas ou n'est plus disponible."
  } else if (e?.status === 410) {
    errorTitle.value = 'Lien expiré'
    errorMessage.value =
      'Ce lien de partage a expiré ou a été révoqué. Demandez un nouveau lien à son auteur.'
  } else {
    errorTitle.value = 'Erreur'
    errorMessage.value = e?.message || 'Une erreur est survenue.'
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
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('fr-FR', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}
</script>
