<template>
  <!-- One slim line on a phone: « ‹ Résultats ... ‹ 3/12 › ». -->
  <nav
    :aria-label="t('profile.teacher_results.label')"
    class="flex items-center gap-2 md:gap-3 py-1.5 md:py-2.5 bg-surface-0 dark:bg-surface-900 border-b border-surface-200 dark:border-surface-700"
  >
    <Button
      :label="t('profile.teacher_results.back')"
      :icon="isBelowMd ? 'pi pi-chevron-left' : 'pi pi-arrow-left'"
      severity="secondary"
      :outlined="!isBelowMd"
      :text="isBelowMd"
      size="small"
      class="shrink-0"
      :class="{ '-ml-2': isBelowMd }"
      @click="emit('back')"
    />

    <div v-if="chips.length" class="max-md:hidden flex flex-wrap items-center gap-2 min-w-0">
      <span class="text-sm text-surface-500 dark:text-surface-400">{{ t('profile.teacher_page.teachers') }}</span>
      <ul class="flex flex-wrap gap-2" :aria-label="t('search.teacher.filters_label')">
        <li
          v-for="chip in chips"
          :key="chip.key"
          class="px-2.5 py-1 rounded-md text-sm font-semibold"
          :class="
            chip.key === 'instrument'
              ? 'bg-primary-50 text-primary-700 dark:bg-primary-400/15 dark:text-primary-200'
              : 'bg-surface-100 text-surface-700 dark:bg-surface-800 dark:text-surface-200'
          "
        >
          {{ chip.label }}
        </li>
      </ul>
    </div>

    <!-- The count sits between the arrows on a phone, before them otherwise. -->
    <div class="ml-auto flex items-center gap-2 shrink-0">
      <span class="max-md:order-1 text-sm text-surface-600 dark:text-surface-400">
        <template v-if="isBelowMd">
          <span aria-hidden="true" class="font-semibold text-surface-700 dark:text-surface-200">{{ position.position }}/{{ position.total }}</span>
          <span class="sr-only">{{ t('profile.teacher_results.position', { position: position.position, total: position.total }) }}</span>
        </template>
        <i18n-t v-else keypath="profile.teacher_results.position" scope="global">
          <template #position><strong class="text-surface-900 dark:text-surface-0">{{ position.position }}</strong></template>
          <template #total>{{ position.total }}</template>
        </i18n-t>
      </span>
      <Button
        icon="pi pi-chevron-left"
        severity="secondary"
        outlined
        size="small"
        :aria-label="t('profile.teacher_results.previous')"
        :disabled="!position.previous"
        @click="emit('previous')"
      />
      <Button
        icon="pi pi-chevron-right"
        severity="secondary"
        outlined
        size="small"
        class="max-md:order-2"
        :aria-label="nextLabel"
        :disabled="!position.next && !position.hasMore"
        :loading="isLoadingNext"
        @click="emit('next')"
      />
      <span v-if="position.next && !isBelowMd" class="inline-flex items-center gap-2 max-w-40 text-sm text-surface-700 dark:text-surface-200" aria-hidden="true">
        <MemberAvatar :page="nextAvatar" size="w-6! h-6! text-xs!" />
        <span class="truncate">{{ position.next.name }}</span>
      </span>
    </div>
  </nav>
</template>

<script setup>
import Button from 'primevue/button'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useIsBelowMd } from '../../composables/useIsPhone.js'
import MemberAvatar from '../Service/Page/MemberAvatar.vue'

/** The lessons page opened from the teacher search (#1315): back to the results, what was searched, the next teacher. */
const props = defineProps({
  /** `{ key, label }` per active filter. */
  chips: { type: Array, required: true },
  /** searchResultsPosition() */
  position: { type: Object, required: true },
  isLoadingNext: { type: Boolean, default: false }
})

const emit = defineEmits(['back', 'previous', 'next'])
const { t } = useI18n()
const isBelowMd = useIsBelowMd()

const nextLabel = computed(() =>
  props.position.next
    ? t('profile.teacher_results.next_named', { name: props.position.next.name })
    : t('profile.teacher_results.next')
)

const nextAvatar = computed(() => ({
  profile_picture_url: props.position.next.profile_picture_url,
  display_name: props.position.next.name,
  username: props.position.next.username
}))
</script>
