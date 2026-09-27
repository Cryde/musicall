<template>
  <section class="flex flex-col gap-5" aria-labelledby="home-member-announces-title">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
      <div class="flex flex-wrap items-center gap-3">
        <h2 id="home-member-announces-title" class="m-0 text-2xl font-bold text-surface-900 dark:text-surface-0">
          {{ title }}
        </h2>
        <router-link v-if="source === SOURCE_MATCHES" :to="{ name: 'app_user_announces' }" :class="CHIP">
          D'après vos annonces · Gérer
        </router-link>
        <router-link
          v-else-if="criteria"
          :to="{ name: 'app_user_settings_profile_musician' }"
          :class="CHIP"
          :aria-label="`Groupes cherchant : ${criteria.instrumentName}. Modifier mon profil musicien`"
        >
          Groupes cherchant : {{ criteria.instrumentName }} · Modifier
        </router-link>
        <button
          v-if="source !== SOURCE_MATCHES && city && !isChangingCity"
          type="button"
          :class="[CHIP, 'cursor-pointer']"
          :aria-label="`Autour de ${city.name}. Changer de ville`"
          @click="isChangingCity = true"
        >
          <i class="pi pi-map-marker text-xs" aria-hidden="true" />
          Autour de {{ city.name }} · Changer
        </button>
      </div>
      <router-link :to="allAnnouncesRoute" class="inline-flex items-center gap-1.5 font-semibold text-primary hover:underline">
        Toutes les annonces
        <i class="pi pi-arrow-right text-sm" aria-hidden="true" />
      </router-link>
    </div>

    <HomeMemberCityPrompt
      v-if="!isLoading && source !== SOURCE_MATCHES && (!city || isChangingCity)"
      :current-city="isChangingCity ? city : null"
      :cancellable="isChangingCity"
      @saved="handleCitySaved"
      @cancel="isChangingCity = false"
    />

    <p v-if="!isLoading && source !== SOURCE_MATCHES && !criteria" class="m-0 text-surface-700 dark:text-surface-300">
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
import profileApi from '../../../api/user/profile.js'
import { LOOKING_FOR_BAND, musicianSearchRoute } from '../../../utils/homeSearch.js'
import {
  announceCriteriaFor,
  matchAsAnnounce,
  searchResultAsAnnounce
} from '../../../utils/memberHome.js'
import { profileCity } from '../../../utils/profileLocation.js'
import AnnounceCardSkeleton from '../../Skeleton/AnnounceCardSkeleton.vue'
import HomeAnnounceCard from '../HomeAnnounceCard.vue'
import HomeMemberCityPrompt from './HomeMemberCityPrompt.vue'

/**
 * « Annonces pour vous »: first the announces answering the member's own (#1082), each saying why.
 * Otherwise the bands looking for their profile instrument (#1078), the nearest first when their
 * profile has a city, which the block asks for (#1079). Without an instrument, every announce near
 * that city, or the latest ones, and a nudge to fill in a musician profile.
 */
const SOURCE_MATCHES = 'matches'
const SOURCE_SEARCH = 'search'
const SOURCE_LATEST = 'latest'

const CHIP =
  'inline-flex items-center gap-1.5 rounded-full bg-primary-100 dark:bg-primary-400/15 px-3 py-1 text-sm text-primary-800 dark:text-primary-200 hover:underline'

const props = defineProps({
  /** Three next to the band, more when the search is what the page is about. */
  limit: { type: Number, default: 3 },
  /** Per row on a wide screen: two when the block shares its row with the messages. */
  columns: { type: Number, default: 3 }
})

defineEmits(['contact-announce'])

const isLoading = ref(true)
const criteria = ref(null)
const city = ref(null)
const isChangingCity = ref(false)
const announces = ref([])
const source = ref(null)

const title = computed(() => {
  if (source.value === SOURCE_MATCHES || criteria.value) return 'Annonces pour vous'
  return city.value ? 'Annonces près de chez vous' : 'Dernières annonces'
})

const allAnnouncesRoute = computed(() => {
  if (source.value === SOURCE_MATCHES) {
    // Filtered by type only when every match is of the same one.
    const types = new Set(announces.value.map((announce) => announce.type))
    return types.size === 1
      ? { name: 'app_search_musician', query: { type: String([...types][0]) } }
      : { name: 'app_search_musician' }
  }
  if (criteria.value) {
    return musicianSearchRoute({
      lookingFor: LOOKING_FOR_BAND,
      instrument: { id: criteria.value.instrumentId },
      city: city.value
    })
  }
  if (!city.value) return { name: 'app_search_musician' }
  const { name, latitude, longitude } = city.value
  return {
    name: 'app_search_musician',
    query: { lat: String(latitude), lng: String(longitude), location: name }
  }
})

onMounted(async () => {
  try {
    const [matches, musicianProfile, profile] = await Promise.all([
      loadMatches(),
      loadMusicianProfile(),
      loadProfile()
    ])
    criteria.value = announceCriteriaFor(musicianProfile)
    city.value = profileCity(profile)
    if (matches.length > 0) {
      source.value = SOURCE_MATCHES
      announces.value = matches.slice(0, props.limit).map(matchAsAnnounce)
    } else if (criteria.value || city.value) {
      source.value = SOURCE_SEARCH
      announces.value = await loadMatching()
    } else {
      source.value = SOURCE_LATEST
      announces.value = await loadLatest()
    }
  } catch {
    announces.value = []
  } finally {
    isLoading.value = false
  }
})

async function handleCitySaved(savedCity) {
  city.value = savedCity
  isChangingCity.value = false
  source.value = SOURCE_SEARCH
  isLoading.value = true
  try {
    announces.value = await loadMatching()
  } catch {
    announces.value = []
  } finally {
    isLoading.value = false
  }
}

// Without a recent announce of their own a member has none, and the block falls back.
async function loadMatches() {
  try {
    return await musicianAnnounceApi.getMatches()
  } catch {
    return []
  }
}

// A member without a musician profile gets a 404, which is an answer rather than a failure.
async function loadMusicianProfile() {
  try {
    return await musicianProfileApi.getMyMusicianProfile()
  } catch {
    return null
  }
}

// Without a city the block still works, by instrument only.
async function loadProfile() {
  try {
    return await profileApi.getMyProfile()
  } catch {
    return null
  }
}

// Every kind of announce near the member when their musician profile names no instrument.
async function loadMatching() {
  // Flagged as a landing list: the home asking on the member's behalf is not a search they ran (#1075).
  // The search sorts by distance from the city, it does not bound it.
  const data = await searchApi.searchAnnounces({
    type: criteria.value?.type ?? null,
    instrument: criteria.value?.instrumentId ?? null,
    latitude: city.value?.latitude ?? null,
    longitude: city.value?.longitude ?? null,
    location: city.value?.name ?? null,
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
