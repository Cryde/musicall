<template>
  <!-- Full bleed on a phone, where a card inside the page gutter only narrowed the text: the negative
       margins cancel the layout's px-6 py-8 (AppBaseLayout). -->
  <section class="-mx-6 -mt-8 overflow-hidden border-b border-surface-200 dark:border-surface-800 bg-surface-0 dark:bg-surface-900 bg-brand-halo sm:mx-0 sm:mt-0 sm:rounded-3xl sm:border">
    <div class="grid lg:grid-cols-[minmax(0,1.15fr)_minmax(0,0.85fr)] gap-12 items-center p-6 sm:p-10 lg:p-16 xl:p-20">
      <div class="flex flex-col gap-6">
        <h1 class="m-0 text-[2rem] sm:text-5xl xl:text-6xl font-semibold leading-[1.06] tracking-tight">
          <i18n-t keypath="home.hero.title" tag="span" class="text-surface-900 dark:text-white">
            <template #highlight>
              <br />
              <span class="text-primary">{{ t('home.hero.title_highlight') }}</span>
            </template>
          </i18n-t>
        </h1>
        <p class="m-0 text-[15px] sm:text-lg lg:text-xl leading-relaxed text-surface-600 dark:text-surface-300 max-w-xl">
          {{ t('home.hero.intro') }}
        </p>

        <HomeSearchForm />

        <HomeFrequentSearches :searches="frequentSearches" />
      </div>

      <!-- A glimpse of the latest announces. Decorative here, since the same announces are listed,
           reachable, right below: inert keeps their links out of the tab order. -->
      <div
        v-if="previewAnnounces.length > 0"
        class="relative hidden lg:block h-[28rem]"
        aria-hidden="true"
        inert
      >
        <div
          v-for="(announce, index) in previewAnnounces"
          :key="announce.id"
          class="absolute w-[26rem] max-w-full rounded-2xl shadow-[0_8px_24px_rgb(0_0_0/0.08)] dark:shadow-[0_8px_24px_rgb(0_0_0/0.4)]"
          :class="STACK_POSITIONS[index]"
        >
          <HomeAnnounceCard :announce="announce" compact />
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { useI18n } from 'vue-i18n'
import HomeAnnounceCard from './HomeAnnounceCard.vue'
import HomeFrequentSearches from './HomeFrequentSearches.vue'
import HomeSearchForm from './HomeSearchForm.vue'

defineProps({
  /** Up to three announces, oldest at the back. */
  previewAnnounces: { type: Array, default: () => [] },
  /** What enough different people searched for lately (#1075); the row stays out when there is none. */
  frequentSearches: { type: Array, default: () => [] }
})

const { t } = useI18n()

const STACK_POSITIONS = [
  // Dimmed rather than faded: a translucent card would show the text of the one behind it.
  'left-10 top-0 -rotate-2 dark:brightness-[0.6]',
  'left-2 top-32 rotate-[1.5deg] dark:brightness-[0.8]',
  'left-8 top-64'
]
</script>
