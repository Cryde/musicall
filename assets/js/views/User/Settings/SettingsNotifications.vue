<template>
  <div class="flex flex-col gap-6">
    <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0">
      Notifications
    </h2>

    <Message severity="info" :closable="false">
      Les paramètres de notification seront disponibles prochainement.
    </Message>
  </div>
</template>

<script setup>
import Message from 'primevue/message'
</script>
