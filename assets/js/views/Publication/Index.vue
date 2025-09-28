<template>
  <div class="flex justify-end">
    <breadcrumb :items="[{'label':  'Publications'}]" />
  </div>

  <div class="flex md:items-center justify-between gap-1 md:flex-row flex-col">
    <div class="flex flex-col gap-2">
      <h1 class="text-2xl font-semibold leading-tight text-surface-900 dark:text-surface-0">
        Publications
      </h1>
      <div class="text-base leading-tight text-surface-500 dark:text-surface-300">
        Découvrez les news, chroniques, découvertes,... postée sur MusicAll.
      </div>
    </div>
    <div>
      <Button
        label="Poster une découverte"
        icon="pi pi-plus"
        severity="info"
        size="small"
        class="whitespace-nowrap mr-3"
      />
      <Button
        label="Poster une publication"
        icon="pi pi-plus"
        severity="info"
        size="small"
        class="whitespace-nowrap"
      />
    </div>
  </div>

    <div class="flex flex-col md:flex-row gap-5">
        <div class="basis-12/12 md:basis-8/12">
      <div class="flex flex-wrap items-center gap-4 mb-5">
        <div class="flex justify-start items-center gap-4">
          <Button
            ref="sortButton"
            outlined
            severity="secondary"
            icon="pi pi-sort-alt"
            icon-pos="right"
            label="Trier par"
            class="px-3 py-2 border-surface-300 dark:border-surface-600 text-surface-500 dark:text-surface-400"
            @click="toggleSortMenu"
          />
          <Menu
            ref="sortMenu"
            :popup="true"
            :model="sortOptions"
          />
        </div>

        <Select
          v-model="selectCategoryFilter"
          :options="publicationsStore.publicationCategories"
          filter
          optionLabel="title"
          showClear
          placeholder="Selectionnez une catégorie"
          resetFilterOnHide
          emptyFilterMessage="Cette catégorie n'existe pas"
          @change="resetList"
          class="w-full md:w-70"
        >
          <template #option="slotProps">
            <div class="flex items-center">
              <div>{{ slotProps.option.title }}</div>
            </div>
          </template>
        </Select>
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
    <div class="hidden md:block md:basis-4/12" />
  </div>
</template>

<script setup>
import { useInfiniteScroll, useTitle } from '@vueuse/core'
import Button from 'primevue/button'
import Menu from 'primevue/menu'
import Select from 'primevue/select'
import { nextTick, onMounted, onUnmounted, ref } from 'vue'
import { usePublicationsStore } from '../../store/publication/publications.js'
import Breadcrumb from '../Global/Breadcrumb.vue'
import PublicationListItem from './PublicationListItem.vue'

useTitle('Toutes les publications relatives à la musique - MusicAll')

const publicationsStore = usePublicationsStore()

const sortMenu = ref()
const selectCategoryFilter = ref(null)
const currentPage = ref(1)
const orientation = ref('desc')
const fetchedItems = ref()

onMounted(async () => {
  await publicationsStore.loadCategories()
})

const infiniteHandler = async () => {
  fetchedItems.value = await publicationsStore.loadPublications({
    page: currentPage.value,
    slug: selectCategoryFilter.value?.slug,
    orientation: orientation.value
  })
  currentPage.value++
}

const canLoadMore = () => {
  return !fetchedItems.value || !!fetchedItems.value.length
}

const { reset } = useInfiniteScroll(document, infiniteHandler, {
  distance: 1000,
  canLoadMore
})

const resetList = async () => {
  currentPage.value = 1
  fetchedItems.value = undefined
  publicationsStore.resetPublications()
  await nextTick()
  reset()
}

const toggleSortMenu = (event) => {
  sortMenu.value.toggle(event)
}

const sortOptions = ref([
  {
    label: 'Nouveau',
    icon: 'pi pi-calendar-plus',
    command: async () => {
      sortMenu.value.hide()
      orientation.value = 'desc'
      await resetList()
    }
  },
  {
    label: 'Ancien',
    icon: 'pi pi-calendar-minus',
    command: async () => {
      sortMenu.value.hide()
      orientation.value = 'asc'
      await resetList()
    }
  }
])

onUnmounted(() => {
  fetchedItems.value = undefined
  publicationsStore.clear()
})
</script>
