<template>
  <div class="flex justify-end">
    <breadcrumb :items="breadcrumbItems" />
  </div>

  <div class="flex md:items-center justify-between gap-1 md:flex-row flex-col">
    <div class="flex flex-col gap-2">
      <h1 class="text-2xl font-semibold leading-tight text-surface-900 dark:text-surface-0">
        {{ pageHeading }}
      </h1>
      <div class="text-base leading-tight text-surface-500 dark:text-surface-300">
        {{ pageDescription }}
      </div>
    </div>
    <div v-if="!isPhotosCategory">
      <Button
        v-tooltip.bottom="'Ajouter une vidéo YouTube découverte'"
        label="Poster une découverte"
        icon="pi pi-plus"
        severity="info"
        size="small"
        class="whitespace-nowrap mr-3"
        @click="handleOpenDiscoverModal"
      />
      <Button
        v-tooltip.bottom="'Ajouter une publication'"
        label="Poster une publication"
        icon="pi pi-plus"
        severity="info"
        size="small"
        class="whitespace-nowrap"
      />
    </div>
  </div>

  <AddDiscoverModal @published="handleDiscoverPublished" />
  <AuthRequiredModal
    v-model:visible="showAuthModal"
    message="Si vous souhaitez partager une vidéo avec la communauté, vous devez vous connecter."
  />

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
          <Menu ref="sortMenu" :popup="true" :model="sortOptions" />
        </div>

        <Select
          :model-value="selectCategoryFilter"
          :options="allCategories"
          filter
          optionLabel="title"
          showClear
          placeholder="Selectionnez une categorie"
          resetFilterOnHide
          emptyFilterMessage="Cette categorie n'existe pas"
          @change="handleCategoryChange"
          class="w-full md:w-70"
        >
          <template #option="slotProps">
            <div class="flex items-center gap-2">
              <i v-if="slotProps.option.slug === 'photos'" class="pi pi-images text-sm" />
              <div>{{ slotProps.option.title }}</div>
            </div>
          </template>
        </Select>
      </div>

      <div class="self-stretch flex flex-col gap-8">
        <div class="grid grid-cols-1 xl:grid-cols-1 gap-3">
          <template v-if="isPhotosCategory">
            <GalleryListItem
              v-for="gallery in galleriesStore.galleries"
              :key="gallery.slug"
              :slug="gallery.slug"
              :cover-image="getCoverImageUrl(gallery)"
              :title="gallery.title"
              :image-count="gallery.imageCount"
              :author="gallery.author"
              :date="gallery.publicationDatetime"
            />
          </template>
          <template v-else>
            <PublicationListItem
              v-for="publication in publicationsStore.publications"
              :to-route="{ name: 'app_publication_show', params: { slug: publication.slug } }"
              :key="publication.id"
              :cover="publication.cover"
              :title="publication.title"
              :description="publication.description"
              :category="publication.sub_category"
              :author="publication.author"
              :date="publication.publication_datetime"
            />
          </template>
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
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AuthRequiredModal from '../../components/Auth/AuthRequiredModal.vue'
import AddDiscoverModal from '../../components/Publication/AddDiscoverModal.vue'
import { useGalleriesStore } from '../../store/gallery/galleries.js'
import { usePublicationsStore } from '../../store/publication/publications.js'
import { useVideoStore } from '../../store/publication/video.js'
import { useUserSecurityStore } from '../../store/user/security.js'
import Breadcrumb from '../Global/Breadcrumb.vue'
import GalleryListItem from '../Gallery/GalleryListItem.vue'
import PublicationListItem from './PublicationListItem.vue'

const PHOTOS_CATEGORY = { id: 'photos', slug: 'photos', title: 'Photos' }

const route = useRoute()
const router = useRouter()
const publicationsStore = usePublicationsStore()
const galleriesStore = useGalleriesStore()
const videoStore = useVideoStore()
const userSecurityStore = useUserSecurityStore()

const sortMenu = ref()
const isInitialized = ref(false)
const selectCategoryFilter = ref(null)
const currentPage = ref(1)
const orientation = ref('desc')
const fetchedItems = ref()
const showAuthModal = ref(false)

const isPhotosCategory = computed(() => selectCategoryFilter.value?.slug === 'photos')

const allCategories = computed(() => {
  return [PHOTOS_CATEGORY, ...publicationsStore.publicationCategories]
})

const breadcrumbItems = computed(() => {
  const items = [
    {
      label: 'Publications',
      to: selectCategoryFilter.value ? { name: 'app_publications' } : undefined
    }
  ]
  if (selectCategoryFilter.value) {
    items.push({ label: selectCategoryFilter.value.title })
  }
  return items
})

const pageTitle = computed(() => {
  if (selectCategoryFilter.value) {
    return `${selectCategoryFilter.value.title} - Publications - MusicAll`
  }
  return 'Toutes les publications relatives a la musique - MusicAll'
})

const pageHeading = computed(() => {
  if (selectCategoryFilter.value) {
    return `Publications - ${selectCategoryFilter.value.title}`
  }
  return 'Publications'
})

const pageDescription = computed(() => {
  if (isPhotosCategory.value) {
    return 'Decouvrez les galeries photos publiees sur MusicAll.'
  }
  if (selectCategoryFilter.value) {
    return `Decouvrez les ${selectCategoryFilter.value.title.toLowerCase()} publiees sur MusicAll.`
  }
  return 'Decouvrez les news, chroniques, decouvertes,... postees sur MusicAll.'
})

useTitle(pageTitle)

onMounted(async () => {
  await publicationsStore.loadCategories()
  initCategoryFromRoute()
  isInitialized.value = true
})

function initCategoryFromRoute() {
  const slugFromRoute = route.params.slug
  if (slugFromRoute) {
    if (slugFromRoute === 'photos') {
      selectCategoryFilter.value = PHOTOS_CATEGORY
    } else {
      const category = publicationsStore.publicationCategories.find((c) => c.slug === slugFromRoute)
      if (category) {
        selectCategoryFilter.value = category
      }
    }
  }
}

watch(
  () => route.params.slug,
  async (newSlug) => {
    if (newSlug) {
      let category
      if (newSlug === 'photos') {
        category = PHOTOS_CATEGORY
      } else {
        category = publicationsStore.publicationCategories.find((c) => c.slug === newSlug)
      }
      if (category && selectCategoryFilter.value?.slug !== newSlug) {
        selectCategoryFilter.value = category
        await resetList()
      }
    } else if (selectCategoryFilter.value) {
      selectCategoryFilter.value = null
      await resetList()
    }
  }
)

async function handleCategoryChange(event) {
  const selectedCategory = event.value
  if (selectedCategory) {
    await router.push({
      name: 'app_publications_by_category',
      params: { slug: selectedCategory.slug }
    })
  } else {
    await router.push({ name: 'app_publications' })
  }
}

const infiniteHandler = async () => {
  if (!isInitialized.value) {
    return
  }

  if (isPhotosCategory.value) {
    fetchedItems.value = await galleriesStore.loadGalleries({
      page: currentPage.value,
      orientation: orientation.value
    })
  } else {
    fetchedItems.value = await publicationsStore.loadPublications({
      page: currentPage.value,
      slug: selectCategoryFilter.value?.slug,
      orientation: orientation.value
    })
  }
  currentPage.value++
}

const canLoadMore = () => {
  if (!isInitialized.value) {
    return true
  }
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
  galleriesStore.resetGalleries()
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

function handleOpenDiscoverModal() {
  if (!userSecurityStore.isAuthenticated) {
    showAuthModal.value = true
    return
  }
  videoStore.openModal()
}

async function handleDiscoverPublished() {
  await resetList()
}

function getCoverImageUrl(gallery) {
  // coverImage is serialized as a URL string by GalleryImageNormalizer
  if (typeof gallery.coverImage === 'string') {
    return gallery.coverImage
  }
  if (gallery.coverImage?.sizes?.medium) {
    return gallery.coverImage.sizes.medium
  }
  return ''
}

onUnmounted(() => {
  fetchedItems.value = undefined
  publicationsStore.clear()
  galleriesStore.clear()
})
</script>
