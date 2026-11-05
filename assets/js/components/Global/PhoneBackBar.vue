<template>
  <div :class="[VARIANTS[variant].root, sticky && STICKY]">
    <Button :icon="backIcon" text rounded severity="secondary" :aria-label="backLabel" @click="emit('back')" />
    <slot name="title">
      <component :is="VARIANTS[variant].tag" :class="VARIANTS[variant].title">{{ title }}</component>
    </slot>
    <slot name="actions" />
  </div>
</template>

<script setup>
import Button from 'primevue/button'

// The bar over every phone screen of the Band Space: back, what the screen is, and its actions.
defineProps({
  backLabel: { type: String, required: true },
  title: { type: String, default: '' },
  // drawer: in a Drawer's header; screen: 56px edge to edge; page: a page heading
  variant: { type: String, default: 'drawer', validator: (value) => ['drawer', 'screen', 'page'].includes(value) },
  // A screen bar stays under the site header while the page scrolls
  sticky: { type: Boolean, default: false },
  // A form not yet saved is left with ×, not ←
  backIcon: { type: String, default: 'pi pi-arrow-left' }
})

const emit = defineEmits(['back'])

const TITLE = 'min-w-0 flex-1 truncate font-semibold'
const VARIANTS = {
  drawer: { root: 'flex min-w-0 flex-1 items-center gap-1 -ml-2', tag: 'span', title: TITLE },
  screen: {
    root: 'flex h-14 w-full min-w-0 shrink-0 items-center gap-1 border-b border-surface-200 px-1 dark:border-surface-700',
    tag: 'h2',
    title: `m-0 text-[1.0625rem] ${TITLE}`
  },
  page: { root: '-mx-2 flex items-center gap-1', tag: 'h1', title: `text-2xl ${TITLE}` }
}
const STICKY = 'sticky top-16 z-30 bg-surface-0 dark:bg-surface-900'
</script>
