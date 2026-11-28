<template>
  <div class="flex flex-col gap-6 max-w-6xl w-full mx-auto">
    <div>
      <h1 class="text-2xl md:text-3xl font-bold text-surface-900 dark:text-surface-0">{{ t('search.directory.heading') }}</h1>
      <p class="mt-1 max-w-2xl text-surface-600 dark:text-surface-400">{{ t('search.directory.intro') }}</p>
    </div>

    <section aria-labelledby="directory-pros">
      <h2 id="directory-pros" class="mb-3 text-lg font-semibold text-surface-900 dark:text-surface-0">
        {{ t('search.directory.pros') }}
      </h2>
      <Message v-if="hasError" severity="error" :closable="false">{{ t('search.directory.error') }}</Message>
      <ul v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 md:gap-4" :aria-busy="isLoading">
        <li v-for="card in cards" :key="card.key">
          <Skeleton v-if="isLoading" height="11rem" border-radius="1rem" />
          <DirectoryKindCard v-else :kind="card.kind" :count="card.count" />
        </li>
      </ul>
    </section>

    <section aria-labelledby="directory-places">
      <h2 id="directory-places" class="mb-3 text-lg font-semibold text-surface-900 dark:text-surface-0">
        {{ t('search.directory.places') }}
      </h2>
      <div
        class="flex items-center gap-4 px-5 py-4 rounded-2xl border-2 border-dashed border-surface-300 dark:border-surface-600 text-surface-600 dark:text-surface-400"
      >
        <i class="pi pi-building text-xl" aria-hidden="true" />
        <div class="flex-1 min-w-0">
          <p class="font-semibold text-surface-900 dark:text-surface-0">{{ t('search.directory.places_title') }}</p>
          <p class="text-sm">{{ t('search.directory.places_text') }}</p>
        </div>
        <Tag :value="t('search.directory.soon')" severity="secondary" class="shrink-0" />
      </div>
    </section>

    <div
      class="flex flex-wrap sm:flex-nowrap items-center gap-4 p-5 rounded-2xl border bg-surface-0 dark:bg-surface-900 border-surface-200 dark:border-surface-700"
    >
      <span
        class="w-10 h-10 rounded-[10px] inline-flex items-center justify-center shrink-0 bg-surface-100 dark:bg-surface-800 text-surface-600 dark:text-surface-300"
      >
        <i class="pi pi-question" aria-hidden="true" />
      </span>
      <div class="flex-1 min-w-0">
        <h2 class="font-semibold text-surface-900 dark:text-surface-0">{{ t('search.directory.trade_title') }}</h2>
        <p class="text-sm text-surface-600 dark:text-surface-400">{{ t('search.directory.trade_text') }}</p>
      </div>
      <Button
        :label="t('search.directory.trade_cta')"
        icon="pi pi-comment"
        severity="secondary"
        outlined
        class="w-full sm:w-auto shrink-0"
        @click="handleSuggestTrade"
      />
    </div>

    <p class="text-sm text-center text-surface-600 dark:text-surface-400">
      {{ t('search.directory.cta') }}
      <router-link :to="{ name: 'app_user_services' }" class="text-primary-700 dark:text-primary-300 font-medium hover:underline">
        {{ t('search.service.cta_link') }}
      </router-link>
    </p>
  </div>
</template>

<script setup>
import { useTitle } from '@vueuse/core'
import Button from 'primevue/button'
import Message from 'primevue/message'
import Skeleton from 'primevue/skeleton'
import Tag from 'primevue/tag'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import serviceSearchApi from '../../api/service/serviceSearch.js'
import { SERVICE_KINDS } from '../../constants/serviceKinds.js'
import { useFeedbackStore } from '../../store/feedback.js'
import { useUserSecurityStore } from '../../store/user/security.js'
import { directoryCards } from '../../utils/directory.js'
import DirectoryKindCard from './DirectoryKindCard.vue'

const { t } = useI18n()
const userSecurityStore = useUserSecurityStore()
const feedbackStore = useFeedbackStore()

const counts = ref([])
const isLoading = ref(true)
const hasError = ref(false)

const cards = computed(() =>
  directoryCards(SERVICE_KINDS, counts.value, userSecurityStore.isTester)
)

useTitle(() => t('search.directory.title'), { restoreOnUnmount: false })

function handleSuggestTrade() {
  // The space leaves the cursor ready for the trade's name.
  feedbackStore.openDrawer({
    type: 'suggestion',
    message: `${t('search.directory.trade_message')} `
  })
}

onMounted(async () => {
  try {
    counts.value = await serviceSearchApi.getKindCounts()
  } catch {
    hasError.value = true
  } finally {
    isLoading.value = false
  }
})
</script>
