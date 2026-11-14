<template>
  <!-- Its own group, mounted in App.vue, so the notice shows under every layout, the layout-less ones included. -->
  <Toast :group="GROUP" :breakpoints="{ '420px': { width: '92vw' } }">
    <template #message>
      <div class="flex flex-1 flex-col items-start gap-2">
        <p class="m-0">{{ t('shared.new_version.message') }}</p>
        <Button :label="t('shared.new_version.reload')" size="small" @click="handleReload" />
      </div>
    </template>
  </Toast>
</template>

<script setup>
import axios from 'axios'
import Button from 'primevue/button'
import Toast from 'primevue/toast'
import { useToast } from 'primevue/usetoast'
import { onBeforeUnmount, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { START_LOCATION, useRouter } from 'vue-router'
import {
  createVersionWatch,
  fetchDeployedVersion,
  loadOutdatedPagesInFull,
  throttle,
  VERSION_CHECK_INTERVAL_MS,
  VERSION_HEADER
} from '../../utils/appVersion.js'

/*
 * A deploy leaves an open tab on the build it was loaded with (#1105). The tab learns of the new
 * one from the header on its API responses, and from version.json when it comes back to the front
 * or changes page, then loads its next page in full. A tab that stays on one page is offered
 * « Recharger ». Both go through the router guards, so a Band Space page with unsaved edits still
 * asks first, and the page load then fires its own beforeunload warning.
 */

const GROUP = 'new-version'
// version.json sits at the root of the build directory, which is the build's base URL.
const VERSION_URL = `${import.meta.env.BASE_URL}version.json`

const { t } = useI18n()
const router = useRouter()
const toast = useToast()

const versionWatch = createVersionWatch({
  // Baked in by vite.config.js.
  running: __APP_BUILD_ID__,
  onOutdated: () => toast.add({ group: GROUP, severity: 'info' })
})
const checkDeployedVersion = throttle(
  async () =>
    versionWatch.observe(
      await fetchDeployedVersion((url, init) => window.fetch(url, init), VERSION_URL)
    ),
  { now: Date.now, intervalMs: VERSION_CHECK_INTERVAL_MS }
)
let fullPageLoad = null
const cleanups = []

function checkUnlessKnown() {
  if (!versionWatch.isOutdated()) checkDeployedVersion()
}

function handleVisibilityChange() {
  if (document.visibilityState === 'visible') checkUnlessKnown()
}

function observeResponse(response) {
  versionWatch.observe(response?.headers?.[VERSION_HEADER])
}

onMounted(() => {
  // A dev server's build id is not the one in public/build, so only a built front end takes part.
  if (!import.meta.env.PROD) return

  const interceptor = axios.interceptors.response.use(
    (response) => {
      observeResponse(response)
      return response
    },
    (error) => {
      observeResponse(error.response)
      return Promise.reject(error)
    }
  )
  cleanups.push(() => axios.interceptors.response.eject(interceptor))

  fullPageLoad = loadOutdatedPagesInFull(router, {
    isOutdated: versionWatch.isOutdated,
    assign: (url) => window.location.assign(url)
  })
  cleanups.push(fullPageLoad.remove)

  cleanups.push(
    router.afterEach((_to, from, failure) => {
      if (!failure && from !== START_LOCATION) checkUnlessKnown()
    })
  )

  document.addEventListener('visibilitychange', handleVisibilityChange)
  cleanups.push(() => document.removeEventListener('visibilitychange', handleVisibilityChange))
})

onBeforeUnmount(() => {
  for (const cleanup of cleanups) cleanup()
})

function handleReload() {
  fullPageLoad?.reload()
}
</script>
