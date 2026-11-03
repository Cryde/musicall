<template>
  <section
    class="flex flex-col gap-2 sm:gap-4 sm:rounded-2xl sm:border sm:border-surface-200 dark:sm:border-surface-700 sm:bg-surface-0 dark:sm:bg-surface-900 sm:p-5 lg:p-6"
    :aria-labelledby="titleId"
  >
    <div class="flex items-center justify-between gap-3">
      <div class="flex min-w-0 items-baseline gap-2">
        <h2 :id="titleId" class="m-0 text-[17px] sm:text-lg font-semibold text-surface-900 dark:text-surface-0">{{ title }}</h2>
        <slot v-if="isPhone" name="aside" />
      </div>
      <slot v-if="!isPhone" name="aside" />
      <router-link
        v-else-if="linkTo"
        :to="linkTo"
        class="inline-flex shrink-0 items-center gap-1.5 text-sm font-semibold text-primary hover:underline"
      >
        {{ linkLabel }}
        <i class="pi pi-arrow-right text-xs" aria-hidden="true" />
      </router-link>
    </div>
    <!-- On a phone a heading over one card of lines, like the homepage's other lists; the frame is the panel above. -->
    <div :class="isPhone ? PHONE_CARD : 'contents'">
      <slot />
    </div>
    <router-link
      v-if="linkTo && !isPhone"
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
import { useIsPhone } from '../../../composables/useIsPhone.js'

/** One block of the logged in home (#1078): a titled panel, with an optional « see all » link. */
defineProps({
  title: { type: String, required: true },
  linkLabel: { type: String, default: null },
  linkTo: { type: [Object, String], default: null }
})

// Rows bring their own height; an empty-state sentence does not, so it gets some here.
const PHONE_CARD =
  'rounded-2xl border border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-900 px-4 py-1 [&>p]:py-3'

const titleId = useId()
const isPhone = useIsPhone()
</script>
