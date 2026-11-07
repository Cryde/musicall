<template>
  <section
    v-if="content"
    class="relative bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-5"
    :class="{ [OWNER_CARD_CLASS]: editable }"
    aria-labelledby="profile-bands-title"
  >
    <ProfileOwnerPencil v-if="editable" :label="t('profile.owner.edit.bands')" @edit="emit('edit')" />
    <h2 id="profile-bands-title" class="m-0 mb-1 text-[15px] font-bold text-surface-900 dark:text-surface-0">
      {{ t('profile.musician_page.bands') }}
    </h2>
    <ul v-if="content === 'items'" class="list-none m-0 p-0 divide-y divide-surface-200 dark:divide-surface-700">
      <li v-for="(band, index) in bands" :key="`${band.name}-${index}`" class="flex items-start gap-3 py-2.5">
        <span class="flex-1 min-w-0">
          <a
            v-if="band.url"
            :href="band.url"
            target="_blank"
            rel="nofollow noopener ugc"
            class="block text-sm font-semibold text-surface-900 dark:text-surface-0 hover:text-primary truncate"
          >
            {{ band.name }}<span class="sr-only"> {{ t('profile.musician_page.band_link') }}</span>
          </a>
          <span v-else class="block text-sm font-semibold text-surface-900 dark:text-surface-0 truncate">{{ band.name }}</span>
          <span v-if="details(band)" class="block text-xs text-surface-500 dark:text-surface-400">{{ details(band) }}</span>
        </span>
        <span
          class="shrink-0 text-xs font-semibold px-2 py-0.5 rounded-md"
          :class="band.current
            ? 'bg-green-500/15 text-green-700 dark:text-green-400'
            : 'bg-surface-100 text-surface-600 dark:bg-surface-800 dark:text-surface-300'"
        >
          {{ band.current ? t('profile.musician_page.band_current') : t('profile.musician_page.band_past') }}
        </span>
      </li>
    </ul>
    <p v-else class="m-0 mt-2 text-sm text-surface-500 dark:text-surface-400">{{ t('profile.owner.hint.bands') }}</p>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { bandsCurrentFirst } from '../../../utils/musicianBands.js'
import { OWNER_CARD_CLASS, ownerCardContent } from '../../../utils/profileOwner.js'
import ProfileOwnerPencil from './ProfileOwnerPencil.vue'

/** The bands a musician plays or played in (#1313), on the profile since the musician page folded into it (#1317). */
const props = defineProps({
  bands: { type: Array, default: () => [] },
  /** The owner's own musician profile: a pencil, and a hint when there is nothing yet. */
  editable: { type: Boolean, default: false }
})

const emit = defineEmits(['edit'])
const { t } = useI18n()
const bands = computed(() => bandsCurrentFirst(props.bands))
const content = computed(() => ownerCardContent(bands.value, props.editable))

function details(band) {
  return [band.style_name, band.role].filter(Boolean).join(', ')
}
</script>
