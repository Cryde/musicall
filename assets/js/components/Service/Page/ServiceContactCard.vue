<template>
  <!-- The contact and the facts, in view while the visitor reads -->
  <aside class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-5 flex flex-col gap-4">
    <div class="flex items-center gap-3">
      <MemberAvatar :page="page" size="w-11! h-11!" />
      <p class="font-semibold text-surface-900 dark:text-surface-0">{{ page.display_name }}</p>
    </div>
    <Button v-if="isOwnPage" :label="t('profile.service_page.edit')" icon="pi pi-pencil" class="w-full" @click="emit('edit')" />
    <div v-else class="flex flex-col gap-2.5">
      <Button :label="t('profile.service_page.ask_quote')" icon="pi pi-send" severity="info" class="w-full" @click="emit('quote')" />
      <Button :label="t('profile.teacher_page.ask_question')" icon="pi pi-envelope" severity="secondary" outlined class="w-full" @click="emit('question')" />
    </div>
    <dl v-if="facts.length" class="grid grid-cols-[6rem_minmax(0,1fr)] gap-x-3 gap-y-3 text-sm border-t border-surface-200 dark:border-surface-700 pt-4">
      <template v-for="fact in facts" :key="fact.key">
        <dt class="text-surface-500 dark:text-surface-400">{{ fact.label }}</dt>
        <dd v-if="fact.tags" class="flex flex-wrap gap-1.5">
          <Tag v-for="tag in fact.tags" :key="tag" :value="tag" severity="secondary" />
        </dd>
        <dd v-else class="text-surface-900 dark:text-surface-0">{{ fact.text }}</dd>
      </template>
    </dl>
  </aside>
</template>

<script setup>
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import { useI18n } from 'vue-i18n'
import MemberAvatar from './MemberAvatar.vue'

/** A service page's side card (#1410, #1411): the member, how to reach them, and the facts the kind names. */
defineProps({
  page: { type: Object, required: true },
  isOwnPage: { type: Boolean, required: true },
  /** `{ key, label, text }`, or `{ key, label, tags }` for a list of tags: only those said. */
  facts: { type: Array, required: true }
})
/** `edit` for its owner; `quote` and `question` for a visitor. */
const emit = defineEmits(['edit', 'quote', 'question'])

const { t } = useI18n()
</script>
