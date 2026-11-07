<template>
  <div class="flex flex-wrap items-center gap-2">
    <StoreBadge v-for="store in stores" :key="store" :store="store" :placement="placement" :class="SIZES[size]" />
  </div>
</template>

<script setup>
import { mobileAppStores } from '../../utils/mobileApp.js'
import StoreBadge from './StoreBadge.vue'

/** The store badges for this device (#1339): its own store on a phone or a tablet, both on a computer. */
defineProps({
  placement: { type: String, required: true },
  size: { type: String, default: 'md' }
})

const SIZES = { md: 'h-10', lg: 'h-12' }
const stores = mobileAppStores(globalThis.navigator)
</script>
