<template>
  <div
    v-if="showBanner"
    class="fixed bottom-0 left-0 right-0 z-50 bg-surface-900 dark:bg-surface-800 border-t border-surface-700 p-4 md:p-6"
  >
    <div class="max-w-6xl mx-auto flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
      <div class="flex-1">
        <p class="text-surface-0 text-sm md:text-base">
          Nous utilisons des cookies pour améliorer votre expérience et analyser le trafic du site.
          <RouterLink
            :to="{ name: 'app_privacy' }"
            class="text-primary hover:text-primary-emphasis underline"
          >
            En savoir plus
          </RouterLink>
        </p>
      </div>
      <div class="flex items-center gap-3">
        <Button
          label="Refuser"
          severity="secondary"
          outlined
          size="small"
          @click="rejectAll"
        />
        <Button
          label="Accepter"
          size="small"
          @click="acceptAll"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import { ref } from 'vue'
import { useConsent } from 'vue-gtag'

const CONSENT_KEY = 'cookie_consent_choice'
const { acceptAll: gtagAccept, rejectAll: gtagReject } = useConsent()

const hasChoice = localStorage.getItem(CONSENT_KEY) !== null
const showBanner = ref(!hasChoice)

function acceptAll() {
  localStorage.setItem(CONSENT_KEY, 'accepted')
  showBanner.value = false
  gtagAccept()
}

function rejectAll() {
  localStorage.setItem(CONSENT_KEY, 'rejected')
  showBanner.value = false
  gtagReject()
}
</script>
