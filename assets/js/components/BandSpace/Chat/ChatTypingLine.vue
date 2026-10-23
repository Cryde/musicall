<template>
  <!-- Always in the page and always the same height, so the conversation does not jump and a screen
       reader hears it change; polite, so it never cuts in. -->
  <p
    class="flex min-h-5 items-center gap-1.5 m-0 px-4 text-xs italic text-surface-600 dark:text-surface-300"
    aria-live="polite"
  >
    <!-- Three dots bounce ahead of who writes, as in the app. -->
    <span v-if="sentence" class="flex items-center gap-0.5" aria-hidden="true">
      <span
        v-for="delay in DOT_DELAYS"
        :key="delay"
        class="typing-dot size-1 rounded-full bg-current"
        :style="{ animationDelay: delay }"
      />
    </span>
    {{ sentence }}
  </p>
</template>

<script setup>
import { computed } from 'vue'
import { typingSentence } from '../../../utils/chatTyping.js'

const props = defineProps({
  // Who is writing, already named, in the order they started.
  names: { type: Array, required: true }
})

const DOT_DELAYS = ['0ms', '160ms', '320ms']

const sentence = computed(() => typingSentence(props.names))
</script>

<style scoped>
.typing-dot {
  animation: typing-dot 1.1s ease-in-out infinite;
}

@keyframes typing-dot {
  0%,
  60%,
  100% {
    transform: translateY(0);
    opacity: 0.5;
  }
  30% {
    transform: translateY(-3px);
    opacity: 1;
  }
}

/* Still, rather than bounced: the global rule only shortens an animation, which would leave it looping. */
@media (prefers-reduced-motion: reduce) {
  .typing-dot {
    animation: none;
  }
}
</style>
