<template>
  <div class="flex flex-wrap items-center gap-3 bg-surface-50 dark:bg-surface-800 rounded-lg px-4 py-3">
    <span class="text-sm font-semibold text-surface-700 dark:text-surface-200 mr-2">{{ t('publication.share.share') }}</span>
    <Button
      icon="pi pi-facebook"
      severity="secondary"
      rounded
      size="small"
      :aria-label="t('publication.share.on_facebook')"
      v-tooltip.bottom="t('publication.share.facebook')"
      @click="shareToFacebook"
    />
    <Button
      icon="pi pi-twitter"
      severity="secondary"
      rounded
      size="small"
      :aria-label="t('publication.share.on_x')"
      v-tooltip.bottom="t('publication.share.x_twitter')"
      @click="shareToTwitter"
    />
    <Button
      icon="pi pi-linkedin"
      severity="secondary"
      rounded
      size="small"
      :aria-label="t('publication.share.on_linkedin')"
      v-tooltip.bottom="t('publication.share.linkedin')"
      @click="shareToLinkedIn"
    />
    <Button
      icon="pi pi-reddit"
      severity="secondary"
      rounded
      size="small"
      :aria-label="t('publication.share.on_reddit')"
      v-tooltip.bottom="t('publication.share.reddit')"
      @click="shareToReddit"
    />
    <Button
      icon="pi pi-envelope"
      severity="secondary"
      rounded
      size="small"
      :aria-label="t('publication.share.by_email')"
      v-tooltip.bottom="t('publication.share.email')"
      @click="shareByEmail"
    />
    <Button
      icon="pi pi-link"
      severity="secondary"
      rounded
      size="small"
      :aria-label="t('publication.share.copy_link')"
      v-tooltip.bottom="t('publication.share.copy_link')"
      @click="copyLink"
    />
  </div>
</template>

<script setup>
import { trackUmamiEvent } from '@jaseeey/vue-umami-plugin'
import Button from 'primevue/button'
import { useToast } from 'primevue/usetoast'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  url: { type: String, required: true },
  title: { type: String, required: true }
})

const { t } = useI18n()
const toast = useToast()
const encodedUrl = computed(() => encodeURIComponent(props.url))
const encodedTitle = computed(() => encodeURIComponent(props.title))

function openShareWindow(url) {
  trackUmamiEvent('share-click')
  window.open(url, '_blank', 'width=600,height=400,noopener,noreferrer')
}

function shareToFacebook() {
  openShareWindow(`https://www.facebook.com/sharer/sharer.php?u=${encodedUrl.value}`)
}

function shareToTwitter() {
  openShareWindow(
    `https://twitter.com/intent/tweet?url=${encodedUrl.value}&text=${encodedTitle.value}`
  )
}

function shareToLinkedIn() {
  openShareWindow(`https://www.linkedin.com/sharing/share-offsite/?url=${encodedUrl.value}`)
}

function shareToReddit() {
  openShareWindow(
    `https://www.reddit.com/submit?url=${encodedUrl.value}&title=${encodedTitle.value}`
  )
}

function shareByEmail() {
  trackUmamiEvent('share-click')
  window.location.href = `mailto:?subject=${encodedTitle.value}&body=${encodedUrl.value}`
}

async function copyLink() {
  trackUmamiEvent('share-click')
  try {
    await navigator.clipboard.writeText(props.url)
    toast.add({
      severity: 'success',
      summary: t('publication.share.link_copied'),
      detail: t('publication.share.link_copied_detail'),
      life: 3000
    })
  } catch {
    toast.add({
      severity: 'error',
      summary: t('publication.common.error'),
      detail: t('publication.share.link_copy_failed'),
      life: 3000
    })
  }
}
</script>
