<template>
  <div>
    <div v-if="isLoading" class="space-y-3">
      <div v-for="i in 3" :key="i" class="h-20 rounded bg-surface-200 dark:bg-surface-700 animate-pulse" />
    </div>

    <div v-else-if="error" class="rounded border border-red-300 bg-red-50 p-4 text-sm text-red-700">
      {{ error }}
    </div>

    <div v-else-if="totalItems === 0" class="rounded border border-surface-200 dark:border-surface-700 p-6 text-center text-surface-500">
      Aucun résultat pour «&nbsp;{{ term }}&nbsp;»
    </div>

    <template v-else>
      <p class="text-sm text-surface-500 mb-4">
        {{ totalItems }} {{ totalItems > 1 ? 'résultats trouvés' : 'résultat trouvé' }} pour «&nbsp;{{ term }}&nbsp;»
      </p>

      <ul class="flex flex-col gap-3">
        <li
          v-for="result in results"
          :key="result.topic_id"
          class="rounded border border-surface-200 dark:border-surface-700 p-4 hover:border-primary transition-colors"
        >
          <router-link
            :to="{ name: 'forum_topic_item', params: { slug: result.topic_slug } }"
            class="block"
          >
            <div class="text-xs text-surface-500 mb-1">
              {{ result.category_title }} ›
              <router-link
                :to="{ name: 'forum_topic_list', params: { slug: result.forum_slug } }"
                class="hover:underline"
                @click.stop
              >{{ result.forum_title }}</router-link>
            </div>
            <h3 class="text-base font-semibold text-surface-900 dark:text-surface-100">
              <span v-html="highlight(result.topic_title)" />
            </h3>
            <p
              v-if="result.post_snippet"
              class="text-sm text-surface-600 dark:text-surface-300 mt-1"
              v-html="highlight(result.post_snippet)"
            />
            <div class="mt-2 text-xs text-surface-400 flex items-center gap-3">
              <span>
                <i class="pi pi-comments mr-1" />
                {{ result.topic_post_number }}
              </span>
              <span v-if="result.last_post_datetime">
                <i class="pi pi-clock mr-1" />
                {{ formatLastPost(result.last_post_datetime) }}
              </span>
            </div>
          </router-link>
        </li>
      </ul>

      <Paginator
        v-if="totalItems > itemsPerPage"
        :rows="itemsPerPage"
        :total-records="totalItems"
        :first="(page - 1) * itemsPerPage"
        class="mt-6"
        @page="onPage"
      />
    </template>
  </div>
</template>

<script setup>
import { format, parseISO } from 'date-fns'
import { fr } from 'date-fns/locale'
import Paginator from 'primevue/paginator'
import { highlightTerm } from '../../utils/highlight.js'

const props = defineProps({
  term: { type: String, required: true },
  results: { type: Array, required: true },
  totalItems: { type: Number, required: true },
  page: { type: Number, default: 1 },
  itemsPerPage: { type: Number, default: 20 },
  isLoading: { type: Boolean, default: false },
  error: { type: String, default: null }
})

const emit = defineEmits(['page-change'])

function highlight(text) {
  return highlightTerm(text, props.term)
}

function formatLastPost(iso) {
  try {
    return format(parseISO(iso), 'dd MMM yyyy', { locale: fr })
  } catch {
    return iso
  }
}

function onPage(event) {
  emit('page-change', event.page + 1)
}
</script>

<style>
mark {
  background-color: rgba(245, 180, 0, 0.35);
  color: inherit;
  padding: 0 0.1em;
  border-radius: 2px;
}
</style>
