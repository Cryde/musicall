<template>
  <div class="bg-surface-0 dark:bg-surface-900 rounded-2xl p-6">
    <h2 class="text-lg font-semibold text-surface-800 dark:text-surface-100 mb-2">
      {{ t('band_space.settings.section.calendar') }}
    </h2>
    <p class="text-sm text-surface-600 dark:text-surface-300 mb-4">
      {{ t('band_space.calendar_sync.intro') }}
    </p>

    <Message severity="secondary" :closable="false" class="mb-4">
      <ul class="list-disc pl-4 text-sm space-y-1">
        <i18n-t keypath="band_space.calendar_sync.separate_calendar" tag="li">
          <template #separate>
            <strong>{{ t('band_space.calendar_sync.separate_calendar_strong') }}</strong>
          </template>
        </i18n-t>
        <i18n-t keypath="band_space.calendar_sync.events_only" tag="li">
          <template #events>
            <strong>{{ t('band_space.calendar_sync.events_only_strong') }}</strong>
          </template>
        </i18n-t>
        <i18n-t keypath="band_space.calendar_sync.google_refresh" tag="li">
          <template #frequency>
            <strong>{{ t('band_space.calendar_sync.google_refresh_strong') }}</strong>
          </template>
        </i18n-t>
      </ul>
    </Message>

    <div v-if="isLoading" class="flex flex-col gap-2">
      <Skeleton width="100%" height="3rem" borderRadius="0.5rem" />
      <Skeleton width="60%" height="2rem" borderRadius="0.5rem" />
    </div>

    <template v-else>
      <!-- Shown once, right after generating: the address only exists in that response. -->
      <div
        v-if="generatedUrl"
        class="mb-4 rounded-xl border border-primary-200 dark:border-primary-800 bg-primary-50 dark:bg-primary-950/40 p-4"
      >
        <p class="text-sm font-medium text-surface-800 dark:text-surface-100 mb-2">
          {{ t('band_space.calendar_sync.your_link') }}
        </p>
        <div class="flex flex-col sm:flex-row gap-2 mb-3">
          <InputText
            :model-value="generatedUrl"
            readonly
            class="flex-1 font-mono text-xs"
            :aria-label="t('band_space.calendar_sync.link_aria')"
            @focus="selectAll"
          />
          <Button
            :icon="hasCopied ? 'pi pi-check' : 'pi pi-copy'"
            :label="hasCopied ? t('band_space.calendar_sync.copied') : t('band_space.calendar_sync.copy')"
            size="small"
            @click="copyUrl"
          />
        </div>

        <div class="flex flex-wrap gap-2 mb-3">
          <Button
            :label="t('band_space.calendar_sync.add_google')"
            icon="pi pi-google"
            size="small"
            severity="secondary"
            outlined
            :as="'a'"
            :href="googleUrl"
            target="_blank"
            rel="noopener"
          />
          <Button
            :label="t('band_space.calendar_sync.add_apple_outlook')"
            icon="pi pi-calendar-plus"
            size="small"
            severity="secondary"
            outlined
            :as="'a'"
            :href="webcalUrl"
          />
        </div>

        <p class="text-xs text-surface-600 dark:text-surface-400">
          {{ t('band_space.calendar_sync.keep_link') }}
        </p>
      </div>

      <div v-if="feed.is_enabled" class="flex flex-col gap-3">
        <dl class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-sm">
          <div>
            <dt class="text-surface-500 dark:text-surface-400">{{ t('band_space.calendar_sync.created_at') }}</dt>
            <dd class="tabular-nums">{{ formatDateLong(feed.creation_datetime) }}</dd>
          </div>
          <div>
            <dt class="text-surface-500 dark:text-surface-400">{{ t('band_space.shares.last_access') }}</dt>
            <dd class="tabular-nums">
              {{ feed.last_access_datetime ? formatDateLong(feed.last_access_datetime) : t('band_space.calendar_sync.never') }}
            </dd>
          </div>
          <div>
            <dt class="text-surface-500 dark:text-surface-400">{{ t('band_space.shares.access_count') }}</dt>
            <dd class="tabular-nums">{{ feed.access_count }}</dd>
          </div>
        </dl>

        <div class="flex flex-wrap gap-2">
          <Button
            :label="t('band_space.calendar_sync.regenerate_link')"
            icon="pi pi-refresh"
            size="small"
            severity="secondary"
            outlined
            :loading="isSubmitting"
            @click="confirmRegenerate"
          />
          <Button
            :label="t('band_space.shares.revoke')"
            icon="pi pi-trash"
            size="small"
            severity="danger"
            text
            :loading="isSubmitting"
            @click="confirmRevoke"
          />
        </div>
      </div>

      <Button
        v-else
        :label="t('band_space.calendar_sync.generate')"
        icon="pi pi-calendar-plus"
        size="small"
        :loading="isSubmitting"
        @click="generate"
      />
    </template>
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Skeleton from 'primevue/skeleton'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import agendaFeedApi from '../../../api/bandSpace/band-space-agenda-feed.js'
import { formatDateLong } from '../../../utils/date.js'

const route = useRoute()
const confirm = useConfirm()
const toast = useToast()
const { t } = useI18n()

const bandSpaceId = route.params.id
const feed = ref({
  is_enabled: false,
  creation_datetime: null,
  last_access_datetime: null,
  access_count: 0
})
const generatedUrl = ref(null)
const isLoading = ref(true)
const isSubmitting = ref(false)
const hasCopied = ref(false)

/**
 * Apple Calendar and Outlook desktop open their subscribe dialog on a webcal:// link, and Google
 * accepts one inside its own cid parameter. An https:// URL in that parameter is not reliably
 * handled, which is why both buttons start from the same webcal form.
 */
const webcalUrl = computed(() =>
  generatedUrl.value ? generatedUrl.value.replace(/^https?:\/\//, 'webcal://') : null
)

const googleUrl = computed(() =>
  webcalUrl.value
    ? `https://calendar.google.com/calendar/r?cid=${encodeURIComponent(webcalUrl.value)}`
    : null
)

onMounted(loadFeed)

async function loadFeed() {
  isLoading.value = true
  try {
    feed.value = await agendaFeedApi.getFeed(bandSpaceId)
  } catch {
    toast.add({
      severity: 'error',
      summary: t('band_space.common.error'),
      detail: t('band_space.calendar_sync.load_error'),
      life: 4000
    })
  } finally {
    isLoading.value = false
  }
}

async function generate() {
  isSubmitting.value = true
  try {
    const created = await agendaFeedApi.generateFeed(bandSpaceId)
    generatedUrl.value = created.feed_url
    hasCopied.value = false
    await loadFeed()
  } catch {
    toast.add({
      severity: 'error',
      summary: t('band_space.common.error'),
      detail: t('band_space.calendar_sync.generate_error'),
      life: 4000
    })
  } finally {
    isSubmitting.value = false
  }
}

function confirmRegenerate() {
  confirm.require({
    message: t('band_space.calendar_sync.regenerate_message'),
    header: t('band_space.calendar_sync.regenerate_link'),
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: t('band_space.calendar_sync.regenerate'),
    rejectLabel: t('band_space.common.cancel'),
    accept: generate
  })
}

function confirmRevoke() {
  confirm.require({
    message: t('band_space.calendar_sync.revoke_message'),
    header: t('band_space.shares.revoke_tooltip'),
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: t('band_space.shares.revoke'),
    rejectLabel: t('band_space.common.cancel'),
    acceptProps: { severity: 'danger' },
    accept: revoke
  })
}

async function revoke() {
  isSubmitting.value = true
  try {
    await agendaFeedApi.revokeFeed(bandSpaceId)
    generatedUrl.value = null
    await loadFeed()
    toast.add({
      severity: 'success',
      summary: t('band_space.shares.revoked'),
      detail: t('band_space.calendar_sync.revoked_detail'),
      life: 3000
    })
  } catch {
    toast.add({
      severity: 'error',
      summary: t('band_space.common.error'),
      detail: t('band_space.calendar_sync.revoke_error'),
      life: 4000
    })
  } finally {
    isSubmitting.value = false
  }
}

async function copyUrl() {
  try {
    await navigator.clipboard.writeText(generatedUrl.value)
    hasCopied.value = true
  } catch {
    toast.add({
      severity: 'warn',
      summary: t('band_space.calendar_sync.copy_failed'),
      detail: t('band_space.calendar_sync.copy_failed_detail'),
      life: 4000
    })
  }
}

function selectAll(event) {
  event.target.select()
}
</script>
