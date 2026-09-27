<template>
  <section
    class="col-span-full flex flex-col gap-4 rounded-2xl border border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-900 p-5 lg:p-7 max-w-xl mx-auto w-full shadow-lg"
    aria-labelledby="guided-signup-title"
  >
    <h2 id="guided-signup-title" class="m-0 text-xl font-semibold leading-snug text-surface-900 dark:text-surface-0">{{ headline }}</h2>
    <p class="m-0 text-surface-700 dark:text-surface-300">
      Créez votre compte gratuit pour voir tous les profils et leur écrire directement.
    </p>
    <SignupOptions />
  </section>
</template>

<script setup>
import SignupOptions from '../../Auth/SignupOptions.vue'

/**
 * Where a guest's results stop (#1084): in the list rather than in a modal, and back to the same
 * search once signed up, since the criteria are in the address it returns to.
 */
defineProps({
  headline: { type: String, required: true }
})
</script>
