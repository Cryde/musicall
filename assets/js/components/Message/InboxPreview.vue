<template>
  <BottomSheet v-if="isPhone" v-model:visible="open" :title="t('message.index.title')">
    <ConversationPreview />
  </BottomSheet>

  <Popover
    v-else
    ref="popover"
    :pt="{ root: { 'aria-label': t('message.index.title'), onClick: closeOnFollowedLink } }"
    @show="open = true"
    @hide="open = false"
  >
    <div class="-mx-3 -mb-3 w-80 sm:w-96">
      <p class="m-0 px-4 pb-2 text-lg font-semibold">{{ t('message.index.title') }}</p>
      <ConversationPreview />
    </div>
  </Popover>
</template>

<script setup>
import Popover from 'primevue/popover'
import { nextTick, onMounted, useTemplateRef, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { useIsPhone } from '../../composables/useIsPhone.js'
import { followsLinkInPlace } from '../../utils/linkClick.js'
import BottomSheet from '../Global/BottomSheet.vue'
import ConversationPreview from './ConversationPreview.vue'

/**
 * What the header's envelope opens: a sheet from the bottom on a phone, a popover beside the bell's
 * elsewhere. Loaded on the first tap, so the message store stays out of every other page's bundle.
 */
const props = defineProps({
  // The envelope, which the popover points at.
  anchor: { type: Object, default: null }
})

const open = defineModel('open', { type: Boolean, default: false })

const { t } = useI18n()
const route = useRoute()
const isPhone = useIsPhone()
const popover = useTemplateRef('popover')

// The popover is placed by its transition's enter, which never runs for one shown in the tick it is
// mounted in (the first tap, or a rotation from the sheet): wait for its portal to be in the page.
async function syncPopover() {
  await nextTick()
  if (isPhone.value || !popover.value || !props.anchor) return
  if (open.value) popover.value.show({ currentTarget: props.anchor }, props.anchor)
  else popover.value.hide()
}

watch([open, isPhone], syncPopover)
onMounted(syncPopover)

// Following a row or « Voir tous les messages » closes it, the conversation already open included,
// which the route cannot tell. A change of page closes it too.
function closeOnFollowedLink(event) {
  if (followsLinkInPlace(event)) open.value = false
}

watch(
  () => route.fullPath,
  () => {
    open.value = false
  }
)
</script>
