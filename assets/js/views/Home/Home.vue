<template>
  <div class="flex flex-row gap-5">
    <div class="basis-8/12">
      <div class="flex justify-between">
        <h2 class="text-xl font-semibold leading-tight text-surface-900 dark:text-surface-0">
          Dernières publications
        </h2>
      </div>
      <div class="flex flex-wrap justify-end-safe gap-4 mb-10">
        <Button
          label="Poster une découverte"
          icon="pi pi-plus"
          severity="info"
          size="small"
        />
        <Button
          label="Poster une publication"
          icon="pi pi-plus"
          severity="info"
          size="small"
        />
      </div>

      <div class="self-stretch flex flex-col gap-8">
        <div class="grid grid-cols-1 xl:grid-cols-1 gap-3">
          <PublicationListItem
            v-for="publication in publicationsStore.publications"
            :to-route="{name: 'app_publication_show', params: {slug: publication.slug}}"
            :key="publication.id"
            :cover="publication.cover"
            :title="publication.title"
            :description="publication.description"
            :category="publication.sub_category"
            :author="publication.author"
            :date="publication.publication_datetime"
          />
        </div>
      </div>
    </div>
    <div class="basis-4/12">
      <h2 class="text-xl font-semibold leading-tight text-surface-900 dark:text-surface-0">
        Dernières annonces
      </h2>

      <div class="flex flex-wrap justify-end-safe gap-4 mb-10">
        <Button
          label="Poster une annonce"
          icon="pi pi-plus"
          severity="info"
          size="small"
        />
      </div>

      <div class="flex flex-col gap-2">
        <Card
          v-for="announce in musicianAnnounceStore.lastAnnounces"
          :key="announce.id"
        >
          <template #content>
            <template v-if="isTypeBand(announce.type)">
              {{ announce.author.username }} est un
              <strong>
                {{ announce.instrument.musician_name.toLocaleLowerCase() }}
              </strong> et cherche un groupe jouant du
              <strong>
                {{ announce.styles.map(style => style.name.toLocaleLowerCase()).join(', ') }}
              </strong>
              dans les alentours de {{ announce.location_name }}
            </template>
            <template v-if="isTypeMusician(announce.type)">
              {{ announce.author.username }} cherche pour son groupe un
              <strong>
                {{ announce.instrument.musician_name.toLocaleLowerCase() }}
              </strong> jouant du
              <strong>
                {{
                  announce.styles.map(style => style.name.toLocaleLowerCase()).join(', ')
                }}
              </strong> dans les alentours de {{ announce.location_name }}
            </template>
          </template>
        </Card>
      </div>
    </div>
  </div>
</template>
<script setup>
import { useHead } from '@unhead/vue'
import Button from 'primevue/button'
import { onMounted, onUnmounted } from 'vue'
import { TYPES_ANNOUNCE_BAND, TYPES_ANNOUNCE_MUSICIAN } from '../../constants/types.js'
import { useMusicianAnnounceStore } from '../../store/announce/musician.js'
import { usePublicationsStore } from '../../store/publication/publications.js'
import PublicationListItem from '../Publication/PublicationListItem.vue'

useHead({
  title: 'MusicAll, le site de référence au service de la musique'
})

const publicationsStore = usePublicationsStore()
const musicianAnnounceStore = useMusicianAnnounceStore()

onMounted(async () => {
  await publicationsStore.loadPublications({ page: 1 })
  await musicianAnnounceStore.loadLastAnnounces()
})

function isTypeBand(type) {
  return type === TYPES_ANNOUNCE_BAND
}

function isTypeMusician(type) {
  return type === TYPES_ANNOUNCE_MUSICIAN
}

onUnmounted(() => {
  publicationsStore.clear()
  musicianAnnounceStore.clear()
})
</script>
