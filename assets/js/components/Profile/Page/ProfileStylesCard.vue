<template>
  <section
    v-if="content"
    class="relative bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-5"
    :class="{ [OWNER_CARD_CLASS]: editable }"
    aria-labelledby="profile-styles-title"
  >
    <ProfileOwnerPencil v-if="editable" :label="t('profile.owner.edit.styles')" @edit="emit('edit')" />
    <h2 id="profile-styles-title" class="m-0 mb-3 text-[15px] font-bold text-surface-900 dark:text-surface-0">
      {{ t('profile.musician_page.styles') }}
    </h2>
    <ul v-if="content === 'items'" class="flex flex-wrap gap-1.5 list-none m-0 p-0">
      <li
        v-for="style in styles"
        :key="style.id"
        class="inline-flex items-center h-7 px-2.5 rounded-lg text-[13px] font-semibold bg-primary-50 text-primary-700 dark:bg-primary-500/15 dark:text-primary-300"
      >
        {{ style.name }}
      </li>
    </ul>
    <p v-else class="m-0 text-sm text-surface-500 dark:text-surface-400">{{ t('profile.owner.hint.styles') }}</p>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { OWNER_CARD_CLASS, ownerCardContent } from '../../../utils/profileOwner.js'
import ProfileOwnerPencil from './ProfileOwnerPencil.vue'

/** The styles a musician plays (#1317). */
const props = defineProps({
  styles: { type: Array, default: () => [] },
  /** The owner's own musician profile: a pencil, and a hint when there is nothing yet. */
  editable: { type: Boolean, default: false }
})

const emit = defineEmits(['edit'])
const { t } = useI18n()
const content = computed(() => ownerCardContent(props.styles, props.editable))
</script>
