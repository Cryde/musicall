<template>
  <section class="mb-12">
    <div class="flex justify-between items-center mb-6">
      <h2 class="text-2xl font-semibold text-surface-900 dark:text-surface-0">
        Dernières annonces
      </h2>
      <div class="flex gap-2">
        <Button
          label="Poster une annonce"
          icon="pi pi-plus"
          severity="info"
          size="small"
          class="hidden md:inline-flex"
          @click="$emit('open-announce-modal')"
        />
        <router-link :to="{ name: 'app_search_musician' }" aria-label="Voir plus d'annonces">
          <Button label="Voir plus" icon="pi pi-arrow-right" iconPos="right" severity="secondary" text size="small" />
        </router-link>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <template v-if="isLoading">
        <AnnounceCardSkeleton v-for="i in 6" :key="i" />
      </template>
      <FadeList v-else-if="announces.length > 0">
        <Card v-for="announce in announces" :key="announce.id" class="transition-all duration-300 hover:shadow-lg hover:-translate-y-1">
          <template #content>
            <div class="flex gap-3">
              <router-link v-if="!announce.author.deletion_datetime" :to="getProfileRoute(announce)" class="shrink-0 hover:opacity-80 transition-opacity" :aria-label="`Voir le profil de ${getAuthorName(announce.author)}`">
                <Avatar
                  v-if="announce.author.profile_picture_url"
                  :image="announce.author.profile_picture_url"
                  :pt="{ image: { alt: `Photo de ${getAuthorName(announce.author)}` } }"
                  shape="circle"
                  role="img"
                  :aria-label="`Photo de ${getAuthorName(announce.author)}`"
                />
                <Avatar
                  v-else
                  :label="getAuthorName(announce.author).charAt(0).toUpperCase()"
                  :style="getAvatarStyle(getAuthorName(announce.author))"
                  shape="circle"
                  role="img"
                  :aria-label="`Avatar de ${getAuthorName(announce.author)}`"
                />
              </router-link>
              <div v-else class="shrink-0">
                <Avatar
                  :label="getAuthorName(announce.author).charAt(0).toUpperCase()"
                  :style="getAvatarStyle(getAuthorName(announce.author))"
                  shape="circle"
                  role="img"
                  :aria-label="`Avatar de ${getAuthorName(announce.author)}`"
                />
              </div>
              <div class="flex-1">
                <template v-if="isTypeBand(announce.type)">
                  <router-link v-if="!announce.author.deletion_datetime" :to="getProfileRoute(announce)" class="font-semibold hover:text-primary transition-colors">{{ getAuthorName(announce.author) }}</router-link>
                  <span v-else class="font-semibold text-surface-500">{{ getAuthorName(announce.author) }}</span> est un
                  <strong>{{ announce.instrument.musician_name.toLocaleLowerCase() }}</strong>
                  et cherche un groupe jouant du
                  <strong>{{ formatStyles(announce.styles).visible }}</strong>
                  <span
                    v-if="hasMoreStyles(announce.styles)"
                    v-tooltip.top="formatStyles(announce.styles).all"
                    class="text-primary cursor-help"
                  >
                    +{{ formatStyles(announce.styles).remaining }}
                  </span>
                  dans les alentours de {{ announce.location_name }}
                </template>
                <template v-if="isTypeMusician(announce.type)">
                  <router-link v-if="!announce.author.deletion_datetime" :to="getProfileRoute(announce)" class="font-semibold hover:text-primary transition-colors">{{ getAuthorName(announce.author) }}</router-link>
                  <span v-else class="font-semibold text-surface-500">{{ getAuthorName(announce.author) }}</span> cherche pour son groupe un
                  <strong>{{ announce.instrument.musician_name.toLocaleLowerCase() }}</strong>
                  jouant du
                  <strong>{{ formatStyles(announce.styles).visible }}</strong>
                  <span
                    v-if="hasMoreStyles(announce.styles)"
                    v-tooltip.top="formatStyles(announce.styles).all"
                    class="text-primary cursor-help"
                  >
                    +{{ formatStyles(announce.styles).remaining }}
                  </span>
                  dans les alentours de {{ announce.location_name }}
                </template>

                <div class="mt-2 text-xs text-surface-500 dark:text-surface-400">
                  <i class="pi pi-clock mr-1" />{{ relativeDate(announce.creation_datetime, { showHours: false }) }}
                </div>

                <div v-if="!isOwnAnnounce(announce) && !announce.author.deletion_datetime" class="mt-2 flex justify-end">
                  <Button
                    size="small"
                    icon="pi pi-envelope"
                    label="Contacter"
                    severity="secondary"
                    text
                    @click="$emit('contact-announce', announce.author)"
                  />
                </div>
              </div>
            </div>
          </template>
        </Card>
      </FadeList>
      <div v-else class="col-span-full text-center py-12 text-surface-500">
        Aucune annonce pour le moment.
      </div>
    </div>

    <div v-if="announces.length > 0" class="text-center mt-6">
      <router-link
        :to="{ name: 'app_search_musician' }"
        class="inline-flex items-center gap-2 text-primary hover:underline font-medium"
      >
        Voir toutes les annonces
        <i class="pi pi-arrow-right text-sm" />
      </router-link>
    </div>
  </section>
</template>

<script setup>
import Avatar from 'primevue/avatar'
import Button from 'primevue/button'
import Card from 'primevue/card'
import { TYPES_ANNOUNCE_BAND, TYPES_ANNOUNCE_MUSICIAN } from '../../constants/types.js'
import relativeDate from '../../helper/date/relative-date.js'
import { displayName } from '../../helper/user/displayName.js'
import { useUserSecurityStore } from '../../store/user/security.js'
import { getAvatarStyle } from '../../utils/avatar.js'
import { formatStyles, hasMoreStyles } from '../../utils/styles.js'
import FadeList from '../Global/FadeList.vue'
import AnnounceCardSkeleton from '../Skeleton/AnnounceCardSkeleton.vue'

defineProps({
  announces: {
    type: Array,
    required: true
  },
  isLoading: {
    type: Boolean,
    default: true
  }
})

defineEmits(['open-announce-modal', 'contact-announce'])

const userSecurityStore = useUserSecurityStore()

function getAuthorName(author) {
  return displayName(author)
}

function isTypeBand(type) {
  return type === TYPES_ANNOUNCE_BAND
}

function isTypeMusician(type) {
  return type === TYPES_ANNOUNCE_MUSICIAN
}

function isOwnAnnounce(announce) {
  return userSecurityStore.userProfile?.id === announce.author.id
}

function getProfileRoute(announce) {
  const route = {
    name: announce.author.has_musician_profile
      ? 'app_user_musician_profile'
      : 'app_user_public_profile',
    params: { username: announce.author.username }
  }
  // Add from query param for contextual back navigation
  route.query = { from: 'home' }
  return route
}
</script>
