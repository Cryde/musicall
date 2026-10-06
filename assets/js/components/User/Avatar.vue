<template>
  <img
    v-if="pictureUrl"
    :src="pictureUrl"
    :alt="label"
    :title="label"
    loading="lazy"
    :class="['rounded-full object-cover', sizeClass]"
  />
  <div
    v-else
    :title="label"
    :class="[
      'rounded-full bg-primary flex items-center justify-center text-primary-contrast font-semibold',
      sizeClass,
      textSizeClass
    ]"
  >
    {{ shownName.charAt(0).toUpperCase() }}
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { userHandle } from '../../utils/userHandle.js'

const props = defineProps({
  username: { type: String, required: true },
  // A band member's name in that band (#1115); the username stays in the label so it can be found
  displayName: { type: String, default: null },
  pictureUrl: { type: String, default: null },
  size: { type: String, default: 'sm', validator: (v) => ['sm', 'md'].includes(v) }
})

const shownName = computed(() => props.displayName || props.username)
const label = computed(() => {
  const handle = userHandle(props.username, props.displayName)
  return handle ? `${props.displayName} (${handle})` : shownName.value
})
const sizeClass = computed(() => (props.size === 'md' ? 'w-7 h-7' : 'w-6 h-6'))
const textSizeClass = computed(() => 'text-[10px]')
</script>
