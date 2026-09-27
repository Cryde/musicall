<template>
  <section class="flex flex-col gap-5" aria-labelledby="home-member-announces-title">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
      <div class="flex flex-wrap items-center gap-3">
        <h2 id="home-member-announces-title" class="m-0 text-2xl font-bold text-surface-900 dark:text-surface-0">
          {{ criteria ? 'Annonces pour vous' : 'Dernières annonces' }}
        </h2>
        <router-link
          v-if="criteria"
          :to="{ name: 'app_user_settings_profile_musician' }"
          class="inline-flex items-center gap-1.5 rounded-full bg-primary-100 dark:bg-primary-400/15 px-3 py-1 text-sm text-primary-800 dark:text-primary-200 hover:underline"
          :aria-label="`Groupes cherchant : ${criteria.instrumentName}. Modifier mon profil musicien`"
        >
          Groupes cherchant : {{ criteria.instrumentName }} · Modifier
        </router-link>
      </div>
      <router-link :to="allAnnouncesRoute" class="inline-flex items-center gap-1.5 font-semibold text-primary hover:underline">
        Toutes les annonces
        <i class="pi pi-arrow-right text-sm" aria-hidden="true" />
      </router-link>
    </div>

    <p v-if="!isLoading && !criteria" class="m-0 text-surface-700 dark:text-surface-300">
      Indiquez vos instruments dans votre
      <router-link :to="{ name: 'app_user_settings_profile_musician' }" class="font-semibold text-primary hover:underline">profil musicien</router-link>
      pour voir ici les groupes qui vous cherchent.
    </p>

    <div :class="['grid grid-cols-1 md:grid-cols-2 gap-4', columns === 3 && 'lg:grid-cols-3']" :aria-busy="isLoading">
      <template v-if="isLoading">
        <AnnounceCardSkeleton v-for="i in limit" :key="i" />
      </template>
      <template v-else-if="announces.length > 0">
        <HomeAnnounceCard
          v-for="announce in announces"
          :key="announce.id"
          :announce="announce"
          @contact="$emit('contact-announce', $event)"
        />
      </template>
      <p v-else class="col-span-full m-0 py-8 text-center text-surface-600 dark:text-surface-300">
        Aucune annonce pour le moment.
      </p>
    </div>
  </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import musicianAnnounceApi from '../../../api/announce/musician.js'
import searchApi from '../../../api/search/musician.js'
import musicianProfileApi from '../../../api/user/musicianProfile.js'
import { LOOKING_FOR_BAND, musicianSearchRoute } from '../../../utils/homeSearch.js'
import { announceCriteriaFor, searchResultAsAnnounce } from '../../../utils/memberHome.js'
import AnnounceCardSkeleton from '../../Skeleton/AnnounceCardSkeleton.vue'
import HomeAnnounceCard from '../HomeAnnounceCard.vue'

/**
 * « Annonces pour vous » (#1078): the bands looking for the member's instrument. By instrument only
 * for now, as nothing knows where a member plays (#1079). Without a musician profile, the latest
 * announces and a nudge to fill one in.
 */
const props = defineProps({
  /** Three next to the band, more when the search is what the page is about. */
  limit: { type: Number, default: 3 },
  /** Per row on a wide screen: two when the block shares its row with the messages. */
  columns: { type: Number, default: 3 }
})

defineEmits(['contact-announce'])

const isLoading = ref(true)
const criteria = ref(null)
const announces = ref([])

const allAnnouncesRoute = computed(() =>
  criteria.value
    ? musicianSearchRoute({
        lookingFor: LOOKING_FOR_BAND,
        instrument: { id: criteria.value.instrumentId }
      })
    : { name: 'app_search_musician' }
)

onMounted(async () => {
  try {
    criteria.value = announceCriteriaFor(await loadMusicianProfile())
    announces.value = criteria.value ? await loadMatching(criteria.value) : await loadLatest()
  } catch {
    announces.value = []
  } finally {
    isLoading.value = false
  }
})

// A member without a musician profile gets a 404, which is an answer rather than a failure.
async function loadMusicianProfile() {
  try {
    return await musicianProfileApi.getMyMusicianProfile()
  } catch {
    return null
  }
}

async function loadMatching({ type, instrumentId }) {
  // Flagged as a landing list: the home asking on the member's behalf is not a search they ran (#1075).
  const data = await searchApi.searchAnnounces({
    type,
    instrument: instrumentId,
    landing: true,
    page: 1
  })
  return data.member.slice(0, props.limit).map(searchResultAsAnnounce)
}

async function loadLatest() {
  const { member } = await musicianAnnounceApi.getLastAnnounces()
  return member.slice(0, props.limit)
}
</script>
