<template>
  <div class="flex justify-end">
    <breadcrumb :items="[{'label':  'Cours'}]"/>
  </div>

  <div class="flex md:items-center justify-between gap-4 md:flex-row flex-col">
    <div class="flex flex-col gap-2">
      <h1 class="text-2xl font-semibold leading-tight text-surface-900 dark:text-surface-0">
        Cours
      </h1>
      <div class="text-base leading-tight text-surface-500 dark:text-surface-300">
        Découvrez les cours publié sur MusicAll.
      </div>
    </div>
    <Button
      label="Poster un cours"
      icon="pi pi-plus"
      severity="info"
      size="small"
      class="whitespace-nowrap"
    />
  </div>

  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-6 mt-2 mb-6">
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

  <div class="flex flex-row">
    <div class="basis-3/4">
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
import { nextTick, onMounted, onUnmounted, ref } from 'vue'
import bassImg from '../../../image/course/basse.png'
import drumImg from '../../../image/course/batterie.png'
import miscImage from '../../../image/course/divers.png'
import guitarImg from '../../../image/course/guitare.png'
import maoImg from '../../../image/course/mao.png'
import ColumnCardRadio from '../../components/RadioGroup/ColumnCardRadio.vue'
import { useCoursesStore } from '../../store/course/course.js'
import Breadcrumb from '../Global/Breadcrumb.vue'
import CourseListItem from './CourseListItem.vue'

useTitle('Liste des catégories de cours - MusicAll')

const coursesStore = useCoursesStore()

const currentPage = ref(1)
const orientation = ref('desc')
const fetchedItems = ref()
const selectCategoryFilter = ref('')
const sortMenu = ref()
const mapInstrumentImage = {
  guitare: guitarImg,
  basse: bassImg,
  batterie: drumImg,
  mao: maoImg,
  divers: miscImage
}

onMounted(async () => {
  await coursesStore.loadCategories()
})

const infiniteHandler = async () => {
  fetchedItems.value = await coursesStore.loadCourses({
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
  coursesStore.resetCourses()
  await nextTick()
  reset()
}

async function changeCategoryFilter(selectedValue) {
  if (selectCategoryFilter.value === selectedValue) {
    await removeFilter()
  } else {
    selectCategoryFilter.value = selectedValue
    await resetList()
  }
}

const removeFilter = async () => {
  selectCategoryFilter.value = ''
  await resetList()
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
  coursesStore.clear()
})
</script>
