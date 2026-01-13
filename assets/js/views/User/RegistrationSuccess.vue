<template>
  <div class="py-10 md:py-20 flex items-center justify-center">
    <div class="max-w-2xl w-full flex flex-col items-center gap-6 bg-surface-0 dark:bg-surface-900 p-4 md:p-12 rounded-3xl text-center">
      <div class="flex items-center justify-center w-20 h-20 rounded-full bg-green-100 dark:bg-green-900/30">
        <i class="pi pi-check text-4xl text-green-600 dark:text-green-400" />
      </div>

      <div class="flex flex-col gap-2">
        <h1 class="text-2xl font-medium text-surface-900 dark:text-surface-0">
          Votre compte est activé !
        </h1>
        <p class="text-surface-500 dark:text-surface-400">
          Vous pouvez désormais vous connecter et profiter de toutes les fonctionnalités de MusicAll.
        </p>
      </div>

      <Button
        label="Se connecter"
        icon="pi pi-sign-in"
        @click="goToLogin"
      />
    </div>
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import { useRouter } from 'vue-router'

const router = useRouter()

function goToLogin() {
  router.push({ name: 'app_login' })
}
</script>
