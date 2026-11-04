<template>
  <Drawer
    v-model:visible="visible"
    position="bottom"
    block-scroll
    :aria-label="title"
    :close-button-props="closeButtonProps"
    :pt="pt"
    @after-hide="returnFocus"
  >
    <template #header>
      <slot name="header">
        <span class="min-w-0 truncate text-lg font-semibold text-surface-900 dark:text-surface-0">{{ title }}</span>
      </slot>
    </template>

    <slot />

    <template v-if="$slots.footer" #footer>
      <slot name="footer" />
    </template>
  </Drawer>
</template>

<script setup>
import Drawer from 'primevue/drawer'
import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { followsLinkInPlace } from '../../utils/linkClick.js'

/**
 * A sheet rising from the bottom of a phone, where the thumb is: the message actions, a conversation's
 * menu, the envelope's latest conversations. PrimeVue's bottom Drawer, sized to its content, with a
 * grabber, closed by a followed link or a change of page, and giving focus back to what opened it.
 */
defineProps({
  title: { type: String, required: true }
})

const visible = defineModel('visible', { type: Boolean, default: false })

const { t } = useI18n()
const route = useRoute()

// PrimeVue names its close button in English whatever the page's language.
const closeButtonProps = computed(() => ({
  severity: 'secondary',
  text: true,
  rounded: true,
  'aria-label': t('shared.sheet.close')
}))

// PrimeVue's styles are unlayered and pin a bottom drawer at 10rem, hence the `!`.
const pt = {
  root: {
    class:
      "!h-auto !max-h-[85dvh] !rounded-t-2xl !border-0 before:absolute before:top-2 before:left-1/2 before:h-1 before:w-9 before:-translate-x-1/2 before:rounded-full before:bg-surface-300 before:content-[''] dark:before:bg-surface-600",
    onClick: closeOnFollowedLink
  },
  header: { class: '!pt-6 !pb-2' },
  content: { class: '!px-0 !pb-[max(1rem,env(safe-area-inset-bottom))]' }
}

function close() {
  visible.value = false
}

// A link followed from the sheet closes it; one opened in another tab leaves it as it is.
function closeOnFollowedLink(event) {
  if (followsLinkInPlace(event)) close()
}

watch(() => route.fullPath, close)

// PrimeVue does not hand focus back: remember what opened the sheet, and return to it. Immediate, for a
// sheet mounted already open, as the envelope's is on the first tap.
let opener = null
watch(
  visible,
  (isOpen) => {
    if (isOpen) opener = document.activeElement
  },
  { immediate: true }
)

function returnFocus() {
  if (opener && document.contains(opener) && document.activeElement === document.body)
    opener.focus()
  opener = null
}

defineExpose({ close })
</script>
