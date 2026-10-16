<template>
  <span v-if="isVisible" class="inline-flex">
    <Button
      icon="pi pi-flag"
      :label="iconOnly ? undefined : t('shared.report.button')"
      text
      :rounded="iconOnly"
      size="small"
      severity="secondary"
      :aria-label="ariaLabel ?? t('shared.report.button_aria_label')"
      v-tooltip.top="iconOnly ? t('shared.report.button') : undefined"
      @click="showDialog = true"
    />
    <ReportDialog v-model:visible="showDialog" :target-type="targetType" :target-id="targetId" />
  </span>
</template>

<script setup>
import Button from 'primevue/button'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useUserSecurityStore } from '../../store/user/security.js'
import { canReportContent } from '../../utils/reportTarget.js'
import ReportDialog from './ReportDialog.vue'

/** « Signaler », offered to a signed-in member on content that is not theirs (#1116). */
const props = defineProps({
  targetType: { type: String, required: true },
  targetId: { type: String, required: true },
  /** One of the two identifies the author, so the button never shows on the viewer's own content. */
  authorId: { type: String, default: null },
  authorUsername: { type: String, default: null },
  ariaLabel: { type: String, default: null },
  iconOnly: { type: Boolean, default: false }
})

const { t } = useI18n()
const userSecurityStore = useUserSecurityStore()
const showDialog = ref(false)

const isVisible = computed(
  () =>
    userSecurityStore.isAuthenticated &&
    canReportContent(
      { id: userSecurityStore.userProfile?.id, username: userSecurityStore.user?.username },
      { id: props.authorId, username: props.authorUsername }
    )
)
</script>
