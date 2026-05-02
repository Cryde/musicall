<template>
  <img
    v-if="pictureUrl"
    :src="pictureUrl"
    :alt="username"
    :title="username"
    loading="lazy"
    :class="['rounded-full object-cover', sizeClass]"
  />
  <div
    v-else
    :title="username"
    :class="[
      'rounded-full bg-primary flex items-center justify-center text-primary-contrast font-semibold',
      sizeClass,
      textSizeClass
    ]"
  >
    {{ username.charAt(0).toUpperCase() }}
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  username: { type: String, required: true },
  pictureUrl: { type: String, default: null },
  size: { type: String, default: 'sm', validator: (v) => ['sm', 'md'].includes(v) }
})

const sizeClass = computed(() => (props.size === 'md' ? 'w-7 h-7' : 'w-6 h-6'))
const textSizeClass = computed(() => 'text-[10px]')
</script>
