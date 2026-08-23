<template>
  <div class="flex-auto py-6 lg:py-8 px-8 lg:px-20">
    <div class="max-w-3xl mx-auto py-12 lg:py-20">
      <div class="flex flex-col items-center text-center">
        <i class="pi pi-users text-6xl text-surface-400 mb-6" aria-hidden="true" />
        <h1 class="text-3xl font-semibold text-surface-900 dark:text-surface-0 mb-3">
          Bienvenue dans les Band Spaces
        </h1>
        <p class="text-surface-600 dark:text-surface-300 text-lg max-w-xl mb-10">
          Un Band Space est l'espace de travail partagé de votre groupe : agenda, tâches, notes, fichiers
          et suivi financier au même endroit, accessible à tous les membres invités.
        </p>

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 w-full mb-12">
          <div
            v-for="module in BAND_SPACE_MODULES"
            :key="module.key"
            class="flex flex-col items-center gap-2 p-4 bg-surface-0 dark:bg-surface-900 rounded-xl"
          >
            <i :class="['text-2xl text-primary', module.icon]" aria-hidden="true" />
            <span class="text-sm font-medium text-surface-700 dark:text-surface-200">{{ module.label }}</span>
          </div>
        </div>

        <div class="flex flex-col sm:flex-row items-center gap-4 mb-8">
          <Button
            label="Créer un Band Space"
            icon="pi pi-plus"
            size="large"
            @click="bandSpaceStore.openCreateModal()"
          />
        </div>

        <div class="flex items-start gap-3 p-4 bg-surface-100 dark:bg-surface-800 rounded-lg max-w-lg">
          <i class="pi pi-envelope text-primary text-xl mt-0.5" aria-hidden="true" />
          <p class="text-sm text-surface-700 dark:text-surface-200 text-left">
            <strong>Vous avez reçu une invitation ?</strong>
            Vérifiez votre boîte mail et cliquez sur le lien d'invitation pour rejoindre un Band Space existant.
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import { BAND_SPACE_MODULES } from '../../constants/bandSpace.js'
import { useBandSpaceStore } from '../../store/bandSpace/bandSpace.js'

const bandSpaceStore = useBandSpaceStore()
</script>
