<template>
  <RouterLink v-if="to" v-slot="{ href, navigate, isExactActive }" :to="to" custom>
    <a
      v-bind="$attrs"
      :href="href"
      :aria-current="isExactActive ? 'page' : undefined"
      :class="[ROW, isExactActive ? ACTIVE : IDLE]"
      @click="navigate"
    >
      <slot name="icon">
        <i :class="[icon, ICON]" aria-hidden="true" />
      </slot>
      <span class="min-w-0 truncate"><slot /></span>
      <slot name="end" />
    </a>
  </RouterLink>
  <button v-else type="button" v-bind="$attrs" :class="[ROW, IDLE]">
    <slot name="icon">
      <i :class="[icon, ICON]" aria-hidden="true" />
    </slot>
    <span class="min-w-0 truncate"><slot /></span>
    <slot name="end" />
  </button>
</template>

<script setup>
/** One line of the phone menu: a link, or a button when it has nowhere to go. Styled like the Band Space sidebar. */
defineOptions({ inheritAttrs: false })

defineProps({
  to: { type: [Object, String], default: null },
  icon: { type: String, default: null }
})

const ROW =
  'flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left font-medium transition-colors duration-150 cursor-pointer'
const IDLE = 'text-surface-700 dark:text-surface-300 hover:bg-surface-100 dark:hover:bg-surface-800'
const ACTIVE = 'bg-primary text-primary-contrast'
const ICON = 'w-5 shrink-0 text-center text-base'
</script>
