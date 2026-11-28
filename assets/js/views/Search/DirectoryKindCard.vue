<template>
  <component
    :is="hasServices ? RouterLink : 'div'"
    :to="hasServices ? { name: kind.search.route } : undefined"
    class="h-full flex flex-col gap-1.5 md:gap-2.5 rounded-2xl border border-surface-200 dark:border-surface-700 p-4 md:p-5"
    :class="
      hasServices
        ? 'bg-surface-0 dark:bg-surface-900 transition-colors hover:border-primary-500 dark:hover:border-primary-400'
        : 'bg-surface-50 dark:bg-surface-900/60'
    "
  >
    <span class="flex items-center gap-3">
      <span :class="['w-10 h-10 rounded-[10px] inline-flex items-center justify-center shrink-0', kind.iconClass]">
        <i :class="[kind.icon, 'text-lg']" aria-hidden="true" />
      </span>
      <span class="text-lg font-semibold text-surface-900 dark:text-surface-0">{{ t(kind.search.menuKey) }}</span>
    </span>

    <!-- A phone keeps the name and the count: seven sentences would make the page a long scroll. -->
    <span class="hidden md:block text-sm text-surface-600 dark:text-surface-400">{{ t(kind.search.introKey) }}</span>

    <span
      class="mt-auto pl-13 md:pl-0 md:pt-3 md:border-t border-surface-200 dark:border-surface-700 flex flex-wrap items-center justify-between gap-x-3 gap-y-2"
    >
      <template v-if="hasServices">
        <i18n-t :keypath="kind.search.countKey" :plural="count" tag="span" class="text-sm text-surface-600 dark:text-surface-400">
          <template #count>
            <b class="text-xl font-bold tabular-nums text-surface-900 dark:text-surface-0">{{ count }}</b>
          </template>
        </i18n-t>
        <i class="pi pi-arrow-right text-primary-600 dark:text-primary-400" aria-hidden="true" />
      </template>
      <template v-else>
        <span class="text-sm text-surface-500 dark:text-surface-400">{{ t('search.directory.nobody_yet') }}</span>
        <Button
          as="router-link"
          :to="{ name: 'app_user_services' }"
          :label="t('search.directory.be_first')"
          icon="pi pi-plus"
          severity="secondary"
          size="small"
        />
      </template>
    </span>
  </component>
</template>

<script setup>
import Button from 'primevue/button'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'

const props = defineProps({
  /** A SERVICE_KINDS entry. */
  kind: { type: Object, required: true },
  /** How many services its search lists. */
  count: { type: Number, required: true }
})

const { t } = useI18n()

// A kind with nobody has no search worth opening: the card asks for its first member instead.
const hasServices = computed(() => props.count > 0)
</script>
