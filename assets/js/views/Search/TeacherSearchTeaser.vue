<template>
  <div class="py-8 md:py-12">
    <!-- Hero Section -->
    <section class="relative overflow-hidden rounded-3xl mb-12 bg-gradient-to-br from-surface-950 via-primary-950/50 to-surface-950">
      <!-- Light mode background -->
      <div class="absolute inset-0 bg-gradient-to-br from-primary-100 via-purple-50 to-primary-100 dark:hidden" />

      <!-- Gradient orbs background -->
      <div class="absolute top-10 left-1/4 w-48 h-48 bg-primary-600/30 rounded-full blur-[80px] animate-drift" />
      <div class="absolute bottom-10 right-1/4 w-64 h-64 bg-fuchsia-600/20 rounded-full blur-[100px] animate-drift" style="animation-delay: -5s;" />

      <!-- Content -->
      <div class="relative z-10 p-6 md:p-12 lg:p-16">
        <div class="flex flex-col lg:flex-row items-center gap-8 lg:gap-12">
          <!-- Text content -->
          <div class="flex-1 text-center lg:text-left">
            <div class="inline-flex items-center gap-2 px-4 py-2 bg-primary/10 dark:bg-primary/20 rounded-full text-primary-600 dark:text-primary-400 text-sm font-medium mb-6">
              <i class="pi pi-clock" aria-hidden="true" />
              {{ t('search.teacher_teaser.coming_soon') }}
            </div>

            <h1 class="text-3xl md:text-4xl lg:text-5xl font-bold mb-6 leading-tight">
              <span class="text-surface-900 dark:text-white">{{ t('search.teacher_teaser.title_lead') }}</span>
              <br />
              <span class="text-brand-gradient">{{ t('search.teacher_teaser.title_highlight') }}</span>
            </h1>

            <p class="text-lg text-surface-600 dark:text-surface-400 mb-8 max-w-lg mx-auto lg:mx-0">
              {{ t('search.teacher_teaser.intro') }}
            </p>

            <!-- Features pills -->
            <div class="flex flex-wrap justify-center lg:justify-start gap-3">
              <div class="px-4 py-2 bg-white/20 dark:bg-white/5 border border-surface-400 dark:border-white/10 rounded-full text-surface-700 dark:text-surface-300 text-sm backdrop-blur-sm">
                <i class="pi pi-map-marker mr-2 text-primary-600 dark:text-primary-400" aria-hidden="true" />{{ t('search.teacher_teaser.feature_location') }}
              </div>
              <div class="px-4 py-2 bg-white/20 dark:bg-white/5 border border-surface-400 dark:border-white/10 rounded-full text-surface-700 dark:text-surface-300 text-sm backdrop-blur-sm">
                <i class="pi pi-volume-up mr-2 text-fuchsia-600 dark:text-fuchsia-400" aria-hidden="true" />{{ t('search.teacher_teaser.feature_instruments') }}
              </div>
              <div class="px-4 py-2 bg-white/20 dark:bg-white/5 border border-surface-400 dark:border-white/10 rounded-full text-surface-700 dark:text-surface-300 text-sm backdrop-blur-sm">
                <i class="pi pi-users mr-2 text-cyan-600 dark:text-cyan-400" aria-hidden="true" />{{ t('search.teacher_teaser.feature_levels') }}
              </div>
            </div>
          </div>

          <!-- Image -->
          <div class="flex-shrink-0 w-full max-w-sm lg:max-w-md">
            <div class="relative">
              <div class="absolute -inset-4 bg-gradient-to-r from-primary-500/20 via-fuchsia-500/20 to-cyan-500/20 rounded-3xl blur-2xl" />
              <img
                :src="heroImage"
                :alt="t('search.teacher_teaser.hero_alt')"
                class="relative w-full h-auto rounded-2xl shadow-2xl"
              />
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Featured Teachers Section -->
    <section v-if="teachers.length" class="mb-12">
      <div class="text-center mb-8">
        <h2 class="text-2xl md:text-3xl font-bold text-surface-900 dark:text-white mb-2">
          {{ t('search.teacher_teaser.featured_title') }}
        </h2>
        <p class="text-surface-600 dark:text-surface-400">
          {{ t('search.teacher_teaser.featured_text') }}
        </p>
      </div>

      <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 md:gap-6">
        <router-link
          v-for="teacher in teachers"
          :key="teacher.username"
          :to="{ name: 'app_user_teacher_profile', params: { username: teacher.username } }"
          class="group block bg-surface-0 dark:bg-surface-900 rounded-2xl border border-surface-200 dark:border-surface-700 p-5 transition-all duration-200 hover:shadow-lg hover:border-primary-300 dark:hover:border-primary-700 hover:-translate-y-1"
        >
          <!-- Avatar -->
          <div class="flex justify-center mb-4">
            <img
              v-if="teacher.profile_picture_url"
              :src="teacher.profile_picture_url"
              :alt="teacher.username"
              class="w-20 h-20 rounded-full object-cover ring-2 ring-surface-200 dark:ring-surface-700 group-hover:ring-primary-400 dark:group-hover:ring-primary-600 transition-all"
            />
            <div
              v-else
              class="w-20 h-20 rounded-full bg-gradient-to-br from-primary-400 to-fuchsia-500 flex items-center justify-center ring-2 ring-surface-200 dark:ring-surface-700 group-hover:ring-primary-400 dark:group-hover:ring-primary-600 transition-all"
            >
              <i class="pi pi-user text-2xl text-white" aria-hidden="true" />
            </div>
          </div>

          <!-- Name -->
          <h3 class="text-center font-semibold text-surface-900 dark:text-surface-0 mb-2 truncate">
            {{ teacher.username }}
          </h3>

          <!-- Instruments -->
          <p v-if="teacher.instruments.length" class="text-center text-sm text-surface-600 dark:text-surface-400 mb-3 line-clamp-2">
            {{ teacher.instruments.map(i => i.instrument_name).join(', ') }}
          </p>

          <!-- Free trial badge -->
          <div v-if="teacher.offers_trial && teacher.trial_price === 0" class="flex justify-center">
            <span class="inline-flex items-center gap-1 px-3 py-1 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 text-xs font-medium rounded-full">
              <i class="pi pi-check-circle" aria-hidden="true" />
              {{ t('search.teacher_teaser.free_trial') }}
            </span>
          </div>
        </router-link>
      </div>
    </section>

    <!-- CTA Section for Teachers -->
    <section class="max-w-3xl mx-auto">
      <div class="bg-surface-0 dark:bg-surface-900 rounded-2xl shadow-lg border border-surface-200 dark:border-surface-700 p-8 md:p-10">
        <div class="flex flex-col md:flex-row items-center gap-6 md:gap-10">
          <!-- Icon -->
          <div class="flex-shrink-0">
            <div class="w-20 h-20 rounded-2xl bg-gradient-to-br from-[#6b97be] to-[#4a7599] dark:from-[#7ba7ce] dark:to-[#5b87ae] flex items-center justify-center shadow-lg shadow-[#5b87ae]/30">
              <i class="pi pi-graduation-cap text-3xl text-white" aria-hidden="true" />
            </div>
          </div>

          <!-- Content -->
          <div class="flex-1 text-center md:text-left">
            <h2 class="text-2xl font-bold text-surface-900 dark:text-surface-0 mb-2">
              {{ t('search.teacher_teaser.cta_title') }}
            </h2>
            <p class="text-surface-600 dark:text-surface-400 mb-6">
              {{ t('search.teacher_teaser.cta_text') }}
            </p>
            <Button
              :label="t('search.teacher_teaser.cta_button')"
              icon="pi pi-arrow-right"
              iconPos="right"
              size="large"
              class="!bg-gradient-to-r !from-[#6b97be] !to-[#4a7599] dark:!from-[#7ba7ce] dark:!to-[#5b87ae] hover:!from-[#5b87ae] hover:!to-[#3a6589] dark:hover:!from-[#8bb7de] dark:hover:!to-[#6b97be] !border-0 !shadow-xl !shadow-[#5b87ae]/30 dark:!shadow-[#5b87ae]/40 hover:!shadow-[#5b87ae]/50"
              @click="handleCreateProfile"
            />
          </div>
        </div>
      </div>

      <!-- Secondary Link -->
      <div class="text-center mt-8">
        <p class="text-surface-500 dark:text-surface-400 mb-3">
          {{ t('search.teacher_teaser.musician_question') }}
        </p>
        <router-link
          :to="{ name: 'app_search_musician' }"
          class="inline-flex items-center gap-2 text-primary-600 dark:text-primary-400 hover:underline font-medium"
        >
          <i class="pi pi-users" aria-hidden="true" />
          {{ t('search.teacher_teaser.search_musician') }}
        </router-link>
      </div>
    </section>

    <!-- Auth Modal -->
    <AuthRequiredModal v-model:visible="showAuthModal" :message="authModalMessage" />
  </div>
</template>

<script setup>
import { useTitle } from '@vueuse/core'
import Button from 'primevue/button'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import heroImage1 from '../../../image/teacher-teaser/1.webp'
import heroImage2 from '../../../image/teacher-teaser/2.webp'
import heroImage3 from '../../../image/teacher-teaser/3.webp'
import teacherProfileApi from '../../api/user/teacherProfile.js'
import AuthRequiredModal from '../../components/Auth/AuthRequiredModal.vue'
import { useUserSecurityStore } from '../../store/user/security.js'

const { t } = useI18n()

useTitle(computed(() => t('search.teacher_teaser.page_title')))

const router = useRouter()
const userSecurityStore = useUserSecurityStore()

const showAuthModal = ref(false)
const authModalMessage = ref('')
const teachers = ref([])

// Randomly select one of the 3 images
const heroImages = [heroImage1, heroImage2, heroImage3]
const heroImage = heroImages[Math.floor(Math.random() * heroImages.length)]

onMounted(async () => {
  try {
    const data = await teacherProfileApi.getFeaturedTeachers()
    teachers.value = data.member
  } catch {
    // silently fail
  }
})

function handleCreateProfile() {
  if (!userSecurityStore.isAuthenticated) {
    authModalMessage.value = t('search.teacher_teaser.login_required')
    showAuthModal.value = true
    return
  }
  router.push({ name: 'app_user_settings_profile_teacher' })
}
</script>
