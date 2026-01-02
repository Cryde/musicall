<template>
  <div class="flex justify-end">
    <breadcrumb :items="breadcrumbItems"/>
  </div>

  <div class="flex md:items-center justify-between gap-4 md:flex-row flex-col">
    <div class="flex flex-col gap-2">
      <h1 class="text-2xl font-semibold leading-tight text-surface-900 dark:text-surface-0">
        {{ pageHeading }}
      </h1>
      <div class="text-base leading-tight text-surface-500 dark:text-surface-300">
        {{ pageDescription }}
      </div>
    </div>
    <Button
      v-tooltip.bottom="'Ajouter un cours vidéo YouTube'"
      label="Poster un cours"
      icon="pi pi-plus"
      severity="info"
      size="small"
      class="whitespace-nowrap"
      @click="handleOpenCourseModal"
    />
  </div>

  <AddCourseVideoModal
    v-model:visible="showCourseModal"
    :categories="coursesStore.courseCategories"
    @published="handleCoursePublished"
  />
  <AuthRequiredModal
    v-model:visible="showAuthModal"
    message="Si vous souhaitez partager un cours vidéo avec la communauté, vous devez vous connecter."
  />

  <div class="grid grid-cols-3 sm:grid-cols-2 lg:grid-cols-5 gap-3 sm:gap-6 mt-2 mb-6">
    <ColumnCardRadio
      v-for="(category, index) in coursesStore.courseCategories"
      v-ripple
      :key="index"
      :title="category.title"
      :slug="category.slug"
      :current-selected-slug="selectCategoryFilter.slug"
      :imageSrc="mapInstrumentImage[category.slug]"
      @select-item="changeCategoryFilter(category)"
    />
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

        <Chip
            v-if="selectCategoryFilter"
            :label="selectCategoryFilter.title"
            removable
            class="h-auto px-6 rounded-full"
            remove-icon="pi pi-times"
            @remove="removeFilter()"
        />
      </div>

      <div class="self-stretch flex flex-col gap-8">
        <div class="grid grid-cols-1 xl:grid-cols-1 gap-3">
          <template v-if="coursesStore.courses.length === 0 && !fetchedItems">
            <PublicationListItemSkeleton v-for="i in 5" :key="i" />
          </template>
          <CourseListItem
              v-for="course in coursesStore.courses"
              :to-route="{name: 'app_course_show', params: {slug: course.slug}}"
              :key="course.id"
              :cover="course.cover"
              :title="course.title"
              :description="course.description"
              :category="course.sub_category"
              :author="course.author"
              :date="course.publication_datetime"/>
        </div>
      </div>
    </div>
    <div class="basis-1/4" />
  </div>
</template>

<script setup>
import { useInfiniteScroll, useTitle } from '@vueuse/core'
import Button from 'primevue/button'
import Chip from 'primevue/chip'
import Menu from 'primevue/menu'
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import bassImg from '../../../image/course/basse.png'
import drumImg from '../../../image/course/batterie.png'
import miscImage from '../../../image/course/divers.png'
import guitarImg from '../../../image/course/guitare.png'
import maoImg from '../../../image/course/mao.png'
import AuthRequiredModal from '../../components/Auth/AuthRequiredModal.vue'
import AddCourseVideoModal from '../../components/Course/AddCourseVideoModal.vue'
import ColumnCardRadio from '../../components/RadioGroup/ColumnCardRadio.vue'
import PublicationListItemSkeleton from '../../components/Skeleton/PublicationListItemSkeleton.vue'
import { useCoursesStore } from '../../store/course/course.js'
import { useUserSecurityStore } from '../../store/user/security.js'
import Breadcrumb from '../Global/Breadcrumb.vue'
import CourseListItem from './CourseListItem.vue'

const route = useRoute()
const router = useRouter()
const coursesStore = useCoursesStore()
const userSecurityStore = useUserSecurityStore()

const currentPage = ref(1)
const orientation = ref('desc')
const fetchedItems = ref()
const selectCategoryFilter = ref('')
const sortMenu = ref()
const showCourseModal = ref(false)
const showAuthModal = ref(false)
const isInitialized = ref(false)
const mapInstrumentImage = {
  guitare: guitarImg,
  basse: bassImg,
  batterie: drumImg,
  mao: maoImg,
  divers: miscImage
}

const breadcrumbItems = computed(() => {
  const items = [
    { label: 'Cours', to: selectCategoryFilter.value ? { name: 'app_course' } : undefined }
  ]
  if (selectCategoryFilter.value) {
    items.push({ label: selectCategoryFilter.value.title })
  }
  return items
})

const pageTitle = computed(() => {
  if (selectCategoryFilter.value) {
    return `Cours de ${selectCategoryFilter.value.title} - MusicAll`
  }
  return 'Liste des catégories de cours - MusicAll'
})

const pageHeading = computed(() => {
  if (selectCategoryFilter.value) {
    return `Cours de ${selectCategoryFilter.value.title}`
  }
  return 'Cours'
})

const pageDescription = computed(() => {
  if (selectCategoryFilter.value) {
    return `Découvrez les cours de ${selectCategoryFilter.value.title.toLowerCase()} publiés sur MusicAll.`
  }
  return 'Découvrez les cours publiés sur MusicAll.'
})

useTitle(pageTitle)

onMounted(async () => {
  await coursesStore.loadCategories()
  initCategoryFromRoute()
  isInitialized.value = true
  // Load initial data
  await infiniteHandler()
})

function initCategoryFromRoute() {
  const slugFromRoute = route.params.slug
  if (slugFromRoute) {
    const category = coursesStore.courseCategories.find((c) => c.slug === slugFromRoute)
    if (category) {
      selectCategoryFilter.value = category
    }
  }
}

watch(
  () => route.params.slug,
  async (newSlug) => {
    if (newSlug) {
      const category = coursesStore.courseCategories.find((c) => c.slug === newSlug)
      if (category && selectCategoryFilter.value?.slug !== newSlug) {
        selectCategoryFilter.value = category
        await resetList()
      }
    } else if (selectCategoryFilter.value) {
      selectCategoryFilter.value = ''
      await resetList()
    }
  }
)

const infiniteHandler = async () => {
  if (!isInitialized.value) {
    return
  }
  fetchedItems.value = await coursesStore.loadCourses({
    page: currentPage.value,
    slug: selectCategoryFilter.value?.slug,
    orientation: orientation.value
  })
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
  coursesStore.resetCourses()
  await nextTick()
  reset()
}

async function changeCategoryFilter(selectedValue) {
  if (selectCategoryFilter.value?.slug === selectedValue.slug) {
    await removeFilter()
  } else {
    await router.push({ name: 'app_course_by_category', params: { slug: selectedValue.slug } })
  }
}

const removeFilter = async () => {
  await router.push({ name: 'app_course' })
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

function handleOpenCourseModal() {
  if (!userSecurityStore.isAuthenticated) {
    showAuthModal.value = true
    return
  }
  showCourseModal.value = true
}

async function handleCoursePublished() {
  await resetList()
}

onUnmounted(() => {
  fetchedItems.value = undefined
  coursesStore.clear()
})
</script>
