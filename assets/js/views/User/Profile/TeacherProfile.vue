<template>
  <div class="py-6 md:py-10">
    <!-- Loading state -->
    <div v-if="isLoading" class="flex justify-center py-12">
      <ProgressSpinner />
    </div>

    <!-- Not found state -->
    <div v-else-if="notFound" class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-8 text-center">
      <i class="pi pi-user-minus text-4xl text-surface-400 mb-4" />
      <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-2">
        {{ t('profile.teacher_page.not_found_title') }}
      </h2>
      <p class="text-surface-500 dark:text-surface-400 mb-4">
        {{ t('profile.teacher_page.not_found_help') }}
      </p>
      <Button
        :label="t('profile.common.view_profile')"
        icon="pi pi-user"
        severity="info"
        @click="$router.push({ name: 'app_user_public_profile', params: { username: route.params.username } })"
      />
    </div>

    <!-- Profile content -->
    <div v-else-if="profile" class="flex flex-col gap-6">
      <!-- Header with back button and profile picture -->
      <div class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-6">
        <div class="flex items-center gap-4">
          <!-- Back button: mobile only -->
          <div class="md:hidden">
            <Button
              icon="pi pi-arrow-left"
              severity="secondary"
              text
              rounded
              :aria-label="t('profile.common.back')"
              @click="handleBack"
            />
          </div>
          <Avatar
            v-if="profile.profile_picture_url"
            :image="profile.profile_picture_url"
            :pt="{ image: { alt: t('profile.common.photo_of', { name: profile.username }) } }"
            size="large"
            shape="circle"
            role="img"
            class="md:!w-16 md:!h-16"
            :aria-label="t('profile.common.photo_of', { name: profile.username })"
          />
          <Avatar
            v-else
            :label="profile.username.charAt(0).toUpperCase()"
            :style="getAvatarStyle(profile.username)"
            size="large"
            shape="circle"
            role="img"
            class="md:!w-16 md:!h-16"
            :aria-label="t('profile.common.avatar_of', { name: profile.username })"
          />
          <div class="flex-1 min-w-0">
            <h1 class="hidden md:block text-2xl font-bold text-surface-900 dark:text-surface-0">
              {{ t('profile.teacher_page.title') }}
            </h1>
            <div class="flex items-center gap-2 flex-wrap">
              <span class="text-surface-500 dark:text-surface-400 truncate">
                @{{ profile.username }}
              </span>
              <Tag
                v-if="primaryInstrument"
                :value="primaryInstrument"
                severity="info"
                class="text-xs"
              />
              <router-link
                :to="{ name: 'app_user_public_profile', params: { username: profile.username } }"
                class="hidden md:inline text-sm text-primary hover:underline"
              >
                {{ t('profile.common.view_profile') }}
              </router-link>
            </div>
          </div>
          <div class="flex gap-2">
            <Button
              v-if="isOwnProfile"
              icon="pi pi-pencil"
              severity="secondary"
              rounded
              v-tooltip.bottom="t('profile.common.edit')"
              :aria-label="t('profile.teacher_page.edit')"
              @click="showEditModal = true"
            />
            <div v-if="!isOwnProfile" class="hidden md:block">
              <ShareButton
                :url="shareUrl"
                :title="shareTitle"
              />
            </div>
            <div v-if="!isOwnProfile" class="md:hidden">
              <Button
                icon="pi pi-envelope"
                rounded
                :aria-label="t('profile.common.contact')"
                @click="handleContact"
              />
            </div>
            <div v-if="!isOwnProfile" class="hidden md:block">
              <Button
                :label="t('profile.common.contact')"
                icon="pi pi-envelope"
                rounded
                @click="handleContact"
              />
            </div>
          </div>
        </div>
      </div>

      <!-- Two columns on desktop -->
      <div class="flex flex-col lg:flex-row gap-6">
        <!-- Left Sidebar -->
        <div class="lg:w-80 shrink-0 flex flex-col gap-6">
          <!-- 1. Instruments -->
          <div v-if="profile.instruments?.length" class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-5">
            <h2 class="text-base font-semibold text-surface-900 dark:text-surface-0 mb-3">
              <i class="pi pi-volume-up mr-2" />{{ t('profile.teacher_page.instruments') }}
            </h2>
            <div class="flex flex-col gap-2">
              <div
                v-for="instrument in profile.instruments"
                :key="instrument.instrument_id"
                class="p-2 rounded-lg bg-surface-50 dark:bg-surface-800"
              >
                <span class="font-medium text-surface-900 dark:text-surface-0 text-sm">
                  {{ instrument.instrument_name }}
                </span>
              </div>
            </div>
          </div>

          <!-- 2. Styles -->
          <div v-if="profile.styles?.length" class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-5">
            <h2 class="text-base font-semibold text-surface-900 dark:text-surface-0 mb-3">
              <i class="pi pi-palette mr-2" />{{ t('profile.teacher_page.styles') }}
            </h2>
            <div class="flex flex-wrap gap-2">
              <Tag
                v-for="style in profile.styles"
                :key="style.id"
                :value="style.name"
                severity="info"
              />
            </div>
          </div>

          <!-- 3. Student levels -->
          <div v-if="profile.student_levels?.length" class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-5">
            <h2 class="text-base font-semibold text-surface-900 dark:text-surface-0 mb-3">
              <i class="pi pi-users mr-2" />{{ t('profile.teacher_page.student_levels') }}
            </h2>
            <div class="flex flex-wrap gap-2">
              <Tag
                v-for="level in profile.student_levels"
                :key="level"
                :value="getStudentLevelLabel(level)"
                :severity="getStudentLevelSeverity(level)"
              />
            </div>
          </div>

          <!-- 4. Experience -->
          <div v-if="profile.years_of_experience" class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-5">
            <h2 class="text-base font-semibold text-surface-900 dark:text-surface-0 mb-3">
              <i class="pi pi-calendar mr-2" />{{ t('profile.teacher_page.experience') }}
            </h2>
            <p class="text-surface-700 dark:text-surface-300">
              {{ t('profile.teacher_page.years_of_teaching', profile.years_of_experience) }}
            </p>
          </div>

          <!-- 5. Age groups -->
          <div v-if="profile.age_groups?.length" class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-5">
            <h2 class="text-base font-semibold text-surface-900 dark:text-surface-0 mb-3">
              <i class="pi pi-id-card mr-2" />{{ t('profile.teacher_page.age_groups') }}
            </h2>
            <div class="flex flex-wrap gap-2">
              <Tag
                v-for="ageGroup in profile.age_groups"
                :key="ageGroup"
                :value="getAgeGroupLabel(ageGroup)"
                severity="secondary"
              />
            </div>
          </div>

          <!-- 6. Locations -->
          <div v-if="profile.locations?.length" class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-5">
            <h2 class="text-base font-semibold text-surface-900 dark:text-surface-0 mb-3">
              <i class="pi pi-map-marker mr-2" />{{ t('profile.teacher_page.locations') }}
            </h2>
            <div class="flex flex-col gap-3">
              <div
                v-for="(loc, index) in profile.locations"
                :key="index"
                class="flex items-start gap-2"
              >
                <i :class="getLocationIcon(loc.type)" class="mt-1 text-surface-500" />
                <div>
                  <span class="font-medium text-surface-700 dark:text-surface-300">{{ getLocationTypeLabel(loc.type) }}</span>
                  <span v-if="loc.city" class="text-surface-600 dark:text-surface-400">
                    : {{ loc.city }}<span v-if="loc.country">, {{ loc.country }}</span>
                  </span>
                  <span v-if="loc.type === 'student_place' && loc.radius" class="text-sm text-surface-500 dark:text-surface-400">
                    ({{ t('profile.teacher.radius_km', { radius: loc.radius }) }})
                  </span>
                  <div v-if="loc.address" class="text-sm text-surface-500 dark:text-surface-400 mt-0.5">
                    {{ loc.address }}
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- 7. Availability -->
          <div v-if="profile.availability?.length" class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-5">
            <h2 class="text-base font-semibold text-surface-900 dark:text-surface-0 mb-3">
              <i class="pi pi-clock mr-2" />{{ t('profile.teacher_page.availability') }}
            </h2>
            <div class="flex flex-col gap-1 text-sm">
              <div
                v-for="(slot, index) in profile.availability"
                :key="index"
                class="flex justify-between py-1 border-b border-surface-100 dark:border-surface-800 last:border-0"
              >
                <span class="font-medium text-surface-700 dark:text-surface-300">{{ getDayOfWeekLabel(slot.day_of_week) }}</span>
                <span class="text-surface-500 dark:text-surface-400">{{ slot.start_time }} - {{ slot.end_time }}</span>
              </div>
            </div>
          </div>

          <!-- 8. Pricing & Packages (merged) -->
          <div v-if="profile.pricing?.length || profile.offers_trial || profile.packages?.length" class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-5">
            <h2 class="text-base font-semibold text-surface-900 dark:text-surface-0 mb-3">
              <i class="pi pi-euro mr-2" />{{ t('profile.teacher_page.pricing') }}
            </h2>

            <!-- Session pricing -->
            <div v-if="profile.pricing?.length" class="flex flex-col gap-2">
              <div
                v-for="pricing in profile.pricing"
                :key="pricing.duration"
                class="flex justify-between items-center"
              >
                <span class="text-surface-600 dark:text-surface-400">{{ getSessionDurationLabel(pricing.duration) }}</span>
                <span class="font-bold text-primary-600 dark:text-primary-400">{{ formatPrice(pricing.price) }}</span>
              </div>
            </div>

            <!-- Packages -->
            <div v-if="profile.packages?.length" :class="{ 'mt-4 pt-4 border-t border-surface-200 dark:border-surface-700': profile.pricing?.length }">
              <div class="text-sm font-medium text-surface-500 dark:text-surface-400 mb-2">{{ t('profile.teacher_page.packages') }}</div>
              <div class="flex flex-col gap-3">
                <div
                  v-for="pkg in profile.packages"
                  :key="pkg.id"
                  class="p-3 rounded-lg bg-surface-50 dark:bg-surface-800"
                >
                  <div class="flex justify-between items-start mb-1">
                    <span class="font-semibold text-surface-900 dark:text-surface-0">{{ pkg.title }}</span>
                    <span class="font-bold text-primary-600 dark:text-primary-400">{{ formatPrice(pkg.price) }}</span>
                  </div>
                  <div v-if="pkg.sessions_count" class="text-sm text-surface-500 dark:text-surface-400">
                    {{ t('profile.teacher_page.sessions', pkg.sessions_count) }}
                  </div>
                  <div v-if="pkg.description" class="text-sm text-surface-600 dark:text-surface-400 mt-1">
                    {{ pkg.description }}
                  </div>
                </div>
              </div>
            </div>

            <!-- Trial offer at the bottom -->
            <div v-if="profile.offers_trial" :class="{ 'mt-4 pt-4 border-t border-surface-200 dark:border-surface-700': profile.pricing?.length || profile.packages?.length }">
              <div class="flex justify-between items-center">
                <span class="text-surface-600 dark:text-surface-400">
                  <i class="pi pi-star text-yellow-500 mr-1" />{{ t('profile.teacher_page.trial') }}
                </span>
                <span class="font-bold text-green-600 dark:text-green-400">
                  {{ profile.trial_price ? formatPrice(profile.trial_price) : t('profile.teacher_page.trial_free') }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col gap-6">
          <!-- Course title -->
          <div v-if="profile.course_title" class="bg-primary-50 dark:bg-primary-900/30 rounded-xl shadow-sm p-6">
            <h2 class="text-xl font-bold text-primary-700 dark:text-primary-300">
              {{ profile.course_title }}
            </h2>
          </div>

          <!-- Description -->
          <div v-if="profile.description" class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-6">
            <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-0 mb-4">
              {{ t('profile.teacher_page.description') }}
            </h2>
            <div class="text-surface-700 dark:text-surface-300 whitespace-pre-line">
              {{ profile.description }}
            </div>
          </div>

          <!-- Media Showcase - only show if has content or is own profile -->
          <TeacherMediaShowcase
            v-if="isOwnProfile || profile.media?.length"
            :is-own-profile="isOwnProfile"
            :media-items="isOwnProfile ? null : profile.media"
            :owner-username="profile.username"
          />

          <!-- Social Links (moved from sidebar) -->
          <div v-if="profile.social_links?.length" class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-6">
            <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-0 mb-4">
              <i class="pi pi-share-alt mr-2" />{{ t('profile.teacher_page.social_links') }}
            </h2>
            <div class="flex flex-wrap gap-3">
              <a
                v-for="link in profile.social_links"
                :key="link.platform"
                :href="link.url"
                target="_blank"
                rel="noopener noreferrer"
                class="flex items-center gap-2 px-4 py-2 rounded-lg bg-surface-50 dark:bg-surface-800 hover:bg-surface-100 dark:hover:bg-surface-700 transition-colors"
              >
                <i :class="getSocialIcon(link.platform)" class="text-lg" :style="{ color: getSocialColor(link.platform) }" />
                <span class="text-surface-700 dark:text-surface-300">{{ getSocialPlatformLabel(link.platform) }}</span>
                <i class="pi pi-external-link text-xs text-surface-400" />
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Edit modal -->
    <EditTeacherProfileModal
      v-model:visible="showEditModal"
      :teacher-profile="profile"
      @saved="handleProfileSaved"
    />

    <!-- Contact modals -->
    <SendMessageModal
      v-model:visible="showMessageModal"
      :selected-recipient="contactRecipient"
      :contact-origin="{ fromTeacherProfile: true }"
    />
    <AuthRequiredModal
      v-model:visible="showAuthModal"
      :message="t('profile.common.contact_auth_required')"
    />
  </div>
</template>

<script setup>
import { useTitle } from '@vueuse/core'
import Avatar from 'primevue/avatar'
import Button from 'primevue/button'
import ProgressSpinner from 'primevue/progressspinner'
import Tag from 'primevue/tag'
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import AuthRequiredModal from '../../../components/Auth/AuthRequiredModal.vue'
import SendMessageModal from '../../../components/Message/SendMessageModal.vue'
import ShareButton from '../../../components/ShareButton.vue'
import EditTeacherProfileModal from '../../../components/Teacher/EditTeacherProfileModal.vue'
import TeacherMediaShowcase from '../../../components/Teacher/TeacherMediaShowcase.vue'
import {
  getAgeGroupLabel,
  getDayOfWeekLabel,
  getLocationTypeLabel,
  getSessionDurationLabel,
  getSocialPlatformLabel,
  getStudentLevelLabel
} from '../../../constants/teacherProfile.js'
import { forActiveLocale } from '../../../i18n/formatLocale.js'
import { useUserSecurityStore } from '../../../store/user/security.js'
import { useTeacherProfileStore } from '../../../store/user/teacherProfile.js'
import { getAvatarStyle } from '../../../utils/avatar.js'
import { formatEurosFromCentsTrimmed } from '../../../utils/currency.js'

const route = useRoute()
const router = useRouter()
const teacherProfileStore = useTeacherProfileStore()
const userSecurityStore = useUserSecurityStore()
const { t } = useI18n()

// Contextual back navigation based on where user came from
function handleBack() {
  const from = route.query.from
  switch (from) {
    case 'search':
    case 'annonces':
      // Use router.back() to preserve KeepAlive state and scroll position
      router.back()
      break
    case 'home':
      router.push({ name: 'app_home' })
      break
    case 'profile':
      router.push({ name: 'app_user_public_profile', params: { username: route.params.username } })
      break
    default:
      // Fallback: try back, or go to public profile
      if (window.history.length > 1) {
        router.back()
      } else {
        router.push({
          name: 'app_user_public_profile',
          params: { username: route.params.username }
        })
      }
  }
}

const isLoading = ref(true)
const notFound = ref(false)
const showEditModal = ref(false)
const showMessageModal = ref(false)
const showAuthModal = ref(false)
const contactRecipient = ref(null)

const profile = computed(() => teacherProfileStore.profile)

const primaryInstrument = computed(() => {
  if (profile.value?.instruments?.length) {
    return profile.value.instruments[0].instrument_name
  }
  return null
})

const isOwnProfile = computed(() => {
  if (!profile.value || !userSecurityStore.userProfile) return false
  return userSecurityStore.userProfile.id === profile.value.user_id
})

const pageTitle = computed(() => {
  if (profile.value) {
    return t('profile.teacher_page.page_title', { username: profile.value.username })
  }
  return t('profile.teacher_page.page_title_default')
})

const shareUrl = computed(() => {
  return window.location.href
})

const shareTitle = computed(() => {
  if (profile.value) {
    return t('profile.teacher_page.share_title', { username: profile.value.username })
  }
  return t('profile.teacher_page.share_title_default')
})

useTitle(pageTitle)

// French as the profile always wrote it, whole euros and the sign glued on; English the Intl way,
// the cents shown only when there are some.
const PRICE_WRITERS = {
  fr: (cents) => `${(cents / 100).toFixed(0)}€`,
  en: formatEurosFromCentsTrimmed
}

function formatPrice(cents) {
  return forActiveLocale(PRICE_WRITERS)(cents)
}

function getStudentLevelSeverity(level) {
  switch (level) {
    case 'beginner':
      return 'secondary'
    case 'intermediate':
      return 'info'
    case 'advanced':
      return 'warn'
    default:
      return 'info'
  }
}

function getLocationIcon(type) {
  switch (type) {
    case 'teacher_place':
      return 'pi pi-home'
    case 'student_place':
      return 'pi pi-car'
    case 'online':
      return 'pi pi-video'
    default:
      return 'pi pi-map-marker'
  }
}

function getSocialIcon(platform) {
  switch (platform) {
    case 'facebook':
      return 'pi pi-facebook'
    case 'instagram':
      return 'pi pi-instagram'
    case 'youtube':
      return 'pi pi-youtube'
    case 'twitter':
      return 'pi pi-twitter'
    case 'linkedin':
      return 'pi pi-linkedin'
    case 'tiktok':
      return 'pi pi-tiktok'
    case 'website':
      return 'pi pi-globe'
    default:
      return 'pi pi-link'
  }
}

function getSocialColor(platform) {
  switch (platform) {
    case 'facebook':
      return '#1877F2'
    case 'instagram':
      return '#E4405F'
    case 'youtube':
      return '#FF0000'
    case 'twitter':
      return '#1DA1F2'
    case 'linkedin':
      return '#0A66C2'
    case 'tiktok':
      return '#000000'
    default:
      return null
  }
}

async function loadProfile() {
  isLoading.value = true
  notFound.value = false

  try {
    await teacherProfileStore.loadPublicProfile(route.params.username)
  } catch (error) {
    if (error.response?.status === 404) {
      notFound.value = true
    } else {
      notFound.value = true
    }
  } finally {
    isLoading.value = false
  }
}

function handleProfileSaved() {
  loadProfile()
}

function handleContact() {
  if (!userSecurityStore.isAuthenticated) {
    showAuthModal.value = true
    return
  }
  contactRecipient.value = {
    id: profile.value.user_id,
    username: profile.value.username
  }
  showMessageModal.value = true
}

watch(
  () => route.params.username,
  (newUsername) => {
    if (newUsername) {
      loadProfile()
    }
  }
)

onMounted(() => {
  loadProfile()
})

onUnmounted(() => {
  teacherProfileStore.clear()
})
</script>
