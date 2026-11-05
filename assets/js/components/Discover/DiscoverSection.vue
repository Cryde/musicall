<template>
  <!-- Text first wherever the two stack; the zigzag only from xl, where they sit side by side. -->
  <div :class="['flex flex-col gap-3.5 md:gap-12 xl:gap-20 items-center', reverse ? 'xl:flex-row-reverse' : 'xl:flex-row']">
    <div class="flex-1">
      <div class="flex items-center gap-3 mb-3.5 md:mb-4">
        <div :class="['w-10 h-10 md:w-12 md:h-12 rounded-xl flex items-center justify-center', iconClass]">
          <i :class="[icon, 'text-lg md:text-2xl']" aria-hidden="true" />
        </div>
        <h2 class="text-2xl md:text-4xl font-bold text-surface-900 dark:text-surface-0">{{ title }}</h2>
      </div>
      <p class="text-surface-600 dark:text-surface-400 text-base leading-[1.6] md:text-xl md:leading-relaxed">
        {{ body }}
      </p>
      <div class="h-px bg-surface-200 dark:bg-surface-800 w-full my-8 max-md:hidden" />
      <!-- Band Space brings its own module grid instead of a list of strings. -->
      <slot name="features">
        <div v-if="features.length" class="flex flex-col gap-2 mt-3.5 md:gap-4 md:mt-0">
          <FeatureItem v-for="feature in features" :key="feature">{{ feature }}</FeatureItem>
        </div>
      </slot>
      <router-link :to="ctaTo" class="inline-block mt-3.5 md:mt-8">
        <Button :label="ctaLabel" icon="pi pi-arrow-right" iconPos="right" />
      </router-link>
    </div>
    <div class="w-full xl:flex-1">
      <div class="relative">
        <div v-if="glowClass" :class="['absolute inset-0 bg-gradient-to-br rounded-2xl blur-2xl -z-10 scale-95', glowClass]" />
        <slot />
      </div>
    </div>
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import FeatureItem from './FeatureItem.vue'

defineProps({
  icon: { type: String, required: true },
  // The tile's background and the icon's colour
  iconClass: { type: String, required: true },
  title: { type: String, required: true },
  body: { type: String, required: true },
  features: { type: Array, default: () => [] },
  ctaLabel: { type: String, required: true },
  ctaTo: { type: Object, required: true },
  // The gradient behind the mock-up
  glowClass: { type: String, default: null },
  // Mock-up on the left, from xl, so the sections alternate
  reverse: { type: Boolean, default: false }
})
</script>
