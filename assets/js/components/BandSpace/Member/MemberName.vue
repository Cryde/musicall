<template>
  <span v-if="!handle">{{ shownName }}</span>
  <span v-else-if="variant === 'tooltip'" v-tooltip.top="handle">
    {{ displayName }}<span class="sr-only"> ({{ handle }})</span>
  </span>
  <span v-else class="inline">
    <button
      type="button"
      class="font-[inherit] text-[inherit] hover:underline focus-visible:underline cursor-pointer"
      aria-haspopup="dialog"
      :aria-expanded="isOpen"
      @click.stop="toggle"
      @keydown.enter.stop
      @keydown.space.stop
    >
      {{ displayName }}<span class="sr-only"> ({{ handle }})</span>
    </button>
    <Popover ref="popover" @show="isOpen = true" @hide="isOpen = false">
      <div class="flex items-center gap-3">
        <Avatar :username="username" :display-name="displayName" :picture-url="pictureUrl" size="md" />
        <div class="min-w-0">
          <p class="text-sm font-semibold text-surface-900 dark:text-surface-0 truncate">{{ displayName }}</p>
          <p class="text-xs text-surface-600 dark:text-surface-400 truncate">{{ handle }}</p>
        </div>
      </div>
    </Popover>
  </span>
</template>

<script setup>
import Popover from 'primevue/popover'
import { computed, ref } from 'vue'
import { memberHandle } from '../../../utils/memberHandle.js'
import Avatar from '../../User/Avatar.vue'

const props = defineProps({
  username: { type: String, required: true },
  displayName: { type: String, default: null },
  pictureUrl: { type: String, default: null },
  // 'tooltip' where a click target would get in the way, such as the chat message header
  variant: {
    type: String,
    default: 'popover',
    validator: (v) => ['popover', 'tooltip'].includes(v)
  }
})

const popover = ref(null)
const isOpen = ref(false)

const shownName = computed(() => props.displayName || props.username)
// Null without a stage name or for a closed account, in which case there is nothing to reveal.
const handle = computed(() => memberHandle(props.username, props.displayName))

function toggle(event) {
  popover.value?.toggle(event)
}
</script>
