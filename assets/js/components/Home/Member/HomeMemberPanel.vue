<template>
  <section
    class="flex flex-col gap-4 rounded-2xl border border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-900 p-5 lg:p-6"
    :aria-labelledby="titleId"
  >
    <div class="flex items-center justify-between gap-3">
      <h2 :id="titleId" class="m-0 text-lg font-bold text-surface-900 dark:text-surface-0">{{ title }}</h2>
      <slot name="aside" />
    </div>
    <slot />
    <router-link
      v-if="linkTo"
      :to="linkTo"
      class="mt-auto inline-flex items-center gap-1.5 self-start text-sm font-semibold text-primary hover:underline"
    >
      {{ linkLabel }}
      <i class="pi pi-arrow-right text-xs" aria-hidden="true" />
    </router-link>
  </section>
</template>

<script setup>
import { useId } from 'vue'

/** One block of the logged in home (#1078): a titled panel, with an optional « see all » link. */
defineProps({
  title: { type: String, required: true },
  linkLabel: { type: String, default: null },
  linkTo: { type: [Object, String], default: null }
})

const titleId = useId()
</script>
