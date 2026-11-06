<template>
  <div class="py-6 md:py-10" :class="{ 'max-md:pb-28': profile && !isOwnProfile }">
    <!-- Loading state -->
    <div v-if="isLoading" class="flex justify-center py-12">
      <ProgressSpinner />
    </div>

    <!-- Private profile state -->
    <PrivateProfileNotice v-else-if="isPrivate" :username="route.params.username" />

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

    <div v-else-if="profile" class="flex flex-col gap-5 md:gap-6">
      <!-- The way back: to the profile when the visitor came from it, otherwise into the teacher search. -->
      <div class="flex items-center gap-2 text-sm text-surface-500 dark:text-surface-400 min-h-10">
        <Button
          icon="pi pi-arrow-left"
          severity="secondary"
          text
          rounded
          class="md:hidden! -ml-2"
          :aria-label="t('profile.common.back')"
          @click="handleBack"
        />
        <router-link
          v-if="cameFromProfile"
          :to="{ name: 'app_user_public_profile', params: { username: profile.username } }"
          class="inline-flex items-center gap-2 hover:text-primary max-md:hidden"
        >
          <i class="pi pi-arrow-left text-xs" aria-hidden="true" />{{ t('profile.teacher_page.back_to_profile', { name: profile.display_name }) }}
        </router-link>
        <nav v-else :aria-label="t('profile.teacher_page.breadcrumb')" class="max-md:hidden">
          <ol class="flex items-center gap-2">
            <li><router-link :to="{ name: 'app_search_teacher' }" class="hover:text-primary">{{ t('profile.teacher_page.teachers') }}</router-link></li>
            <li v-if="firstInstrument" class="flex items-center gap-2">
              <i class="pi pi-chevron-right text-[10px]" aria-hidden="true" />{{ firstInstrument }}
            </li>
            <li v-if="places.city" class="flex items-center gap-2" aria-current="page">
              <i class="pi pi-chevron-right text-[10px]" aria-hidden="true" />{{ places.city }}
            </li>
          </ol>
        </nav>
      </div>

      <!-- Hero: what, where, how much, and something to hear -->
      <section
        class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-5 md:p-7 grid gap-6 items-center"
        :class="{ 'md:grid-cols-[minmax(0,1fr)_minmax(0,380px)]': heroMedia }"
      >
        <div class="min-w-0">
          <div class="flex flex-wrap items-center gap-2 mb-3">
            <span class="inline-flex items-center gap-2 h-8 pl-2.5 pr-3 rounded-full border border-surface-200 dark:border-surface-700 text-sm font-semibold text-surface-700 dark:text-surface-200">
              <i class="pi pi-graduation-cap text-amber-500" aria-hidden="true" />{{ t('profile.teacher_page.teacher_of') }}
            </span>
            <Tag v-for="instrument in profile.instruments" :key="instrument.instrument_id" :value="instrument.instrument_name" severity="info" />
          </div>
          <h1 class="text-[26px] md:text-3xl font-extrabold leading-tight text-surface-900 dark:text-surface-0">
            {{ t('profile.teacher_page.hero_title', { name: profile.display_name }) }}
          </h1>
          <p v-if="profile.course_title" class="mt-2 text-surface-600 dark:text-surface-400 md:text-lg">
            {{ profile.course_title }}
          </p>
          <div
            v-if="placesLine || ageGroupsLine"
            class="flex flex-wrap gap-x-5 gap-y-1 mt-3 text-sm text-surface-600 dark:text-surface-400"
          >
            <span v-if="placesLine" class="inline-flex items-center gap-1.5">
              <i class="pi pi-map-marker text-xs text-surface-400" aria-hidden="true" />{{ placesLine }}
            </span>
            <span v-if="ageGroupsLine" class="inline-flex items-center gap-1.5">
              <i class="pi pi-users text-xs text-surface-400" aria-hidden="true" />{{ ageGroupsLine }}
            </span>
          </div>
          <dl v-if="keyNumbers.length" class="flex flex-wrap gap-x-7 gap-y-3 mt-5">
            <div v-for="item in keyNumbers" :key="item.key" class="flex flex-col-reverse">
              <dt class="text-xs text-surface-500 dark:text-surface-400">{{ item.label }}</dt>
              <dd class="text-xl font-bold text-surface-900 dark:text-surface-0">{{ item.value }}</dd>
            </div>
          </dl>
          <div class="flex flex-wrap items-center gap-2 mt-6">
            <template v-if="isOwnProfile">
              <Button :label="t('profile.teacher_page.edit')" icon="pi pi-pencil" @click="showEditModal = true" />
            </template>
            <template v-else>
              <Button :label="mainActionLabel" icon="pi pi-send" severity="info" size="large" class="max-md:hidden!" @click="openRequest" />
              <Button :label="t('profile.teacher_page.ask_question')" icon="pi pi-envelope" severity="secondary" outlined size="large" @click="handleContact" />
              <ShareButton :url="shareUrl" :title="shareTitle" />
            </template>
          </div>
        </div>

        <button
          v-if="heroMedia"
          type="button"
          class="group block text-left rounded-xl overflow-hidden border border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-800 cursor-pointer"
          :aria-label="heroMedia.title ? t('profile.media.play', { title: heroMedia.title }) : t('profile.media.play_untitled')"
          @click="openMedia(heroMedia)"
        >
          <span class="relative block aspect-video bg-surface-200 dark:bg-surface-700">
            <img
              v-if="heroMedia.thumbnail_url"
              :src="heroMedia.thumbnail_url"
              alt=""
              class="w-full h-full object-cover"
            />
            <span class="absolute inset-0 m-auto w-14 h-14 rounded-full bg-black/60 text-white flex items-center justify-center group-hover:bg-black/75">
              <i class="pi pi-play text-xl" aria-hidden="true" />
            </span>
          </span>
          <span class="block px-3 py-2.5">
            <span class="block text-sm font-semibold text-surface-900 dark:text-surface-0">
              {{ t('profile.teacher_page.listen', { name: profile.display_name }) }}
            </span>
            <span v-if="heroMedia.title" class="block text-xs text-surface-500 dark:text-surface-400 truncate">{{ heroMedia.title }}</span>
          </span>
        </button>
      </section>

      <div class="grid gap-5 md:gap-6 lg:grid-cols-[minmax(0,1fr)_340px] items-start">
        <div class="flex flex-col gap-5 md:gap-6 min-w-0">
          <!-- The musician behind the teacher -->
          <section class="rounded-xl bg-surface-100 dark:bg-surface-800 p-4 md:p-5 flex flex-wrap items-center gap-4">
            <Avatar
              v-if="profile.profile_picture_url"
              :image="profile.profile_picture_url"
              :pt="{ image: { alt: '' } }"
              shape="circle"
              class="w-12! h-12! shrink-0"
            />
            <Avatar
              v-else
              :label="profile.display_name.charAt(0).toUpperCase()"
              :style="getAvatarStyle(profile.username)"
              shape="circle"
              class="w-12! h-12! shrink-0"
            />
            <div class="flex-1 min-w-[12rem]">
              <h2 class="font-semibold text-surface-900 dark:text-surface-0">{{ t('profile.teacher_page.musician_title') }}</h2>
              <p class="text-sm text-surface-600 dark:text-surface-400">
                <template v-if="bandSummary">
                  <i18n-t keypath="profile.teacher_page.plays_in" scope="global">
                    <template #band>
                      <strong class="text-surface-900 dark:text-surface-0">{{ bandSummary.first.name }}</strong>
                      <template v-if="bandSummary.first.style_name"> ({{ bandSummary.first.style_name.toLocaleLowerCase(locale) }})</template>
                      <template v-if="bandSummary.othersCount"> {{ t('profile.teacher_page.other_bands', bandSummary.othersCount) }}</template>
                    </template>
                  </i18n-t>,
                </template>
                {{ musicianFacts }}
              </p>
            </div>
            <ul v-if="profile.musician_instruments?.length" class="flex flex-wrap gap-2" :aria-label="t('profile.teacher_page.musician_instruments')">
              <li
                v-for="instrument in profile.musician_instruments"
                :key="instrument.instrument_id"
                class="inline-flex items-center gap-2 h-8 px-3 rounded-lg bg-surface-0 dark:bg-surface-900 text-sm font-medium text-surface-700 dark:text-surface-200"
              >
                {{ instrument.instrument_name }}
                <span class="flex gap-0.5" role="img" :aria-label="instrument.skill_level_label">
                  <i
                    v-for="step in 4"
                    aria-hidden="true"
                    :key="step"
                    class="block w-2.5 h-1.5 rounded-sm"
                    :class="step <= skillSteps(instrument.skill_level) ? 'bg-primary' : 'bg-surface-200 dark:bg-surface-700'"
                  />
                </span>
              </li>
            </ul>
            <Button
              as="router-link"
              :to="{ name: 'app_user_public_profile', params: { username: profile.username } }"
              :label="t('profile.common.view_profile')"
              severity="secondary"
              outlined
              size="small"
            />
          </section>

          <!-- What you will learn, per accepted level (#1312) -->
          <section v-if="levelGoals.length" class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-5 md:p-6">
            <h2 class="font-semibold text-surface-900 dark:text-surface-0 mb-3 flex items-center gap-2">
              <i class="pi pi-compass text-surface-400" aria-hidden="true" />{{ t('profile.teacher_page.what_you_learn') }}
            </h2>
            <ol
              class="grid rounded-lg border border-surface-200 dark:border-surface-700 divide-y md:divide-y-0 md:divide-x divide-surface-200 dark:divide-surface-700"
              :class="GOAL_COLUMNS[levelGoals.length]"
            >
              <li v-for="goal in levelGoals" :key="goal.level" class="p-4 flex flex-col gap-2">
                <Tag :value="getStudentLevelLabel(goal.level)" :severity="LEVEL_SEVERITY[goal.level]" class="self-start" />
                <h3 v-if="goal.title" class="font-semibold text-surface-900 dark:text-surface-0">{{ goal.title }}</h3>
                <ul v-if="goal.points.length" class="list-disc pl-5 text-sm text-surface-600 dark:text-surface-400 flex flex-col gap-1">
                  <li v-for="(point, index) in goal.points" :key="index">{{ point }}</li>
                </ul>
              </li>
            </ol>
          </section>

          <!-- Where and when -->
          <div v-if="profile.locations?.length || profile.availability?.length" class="grid gap-5 md:gap-6 md:grid-cols-2">
            <section v-if="profile.locations?.length" class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-5">
              <h2 class="font-semibold text-surface-900 dark:text-surface-0 mb-2 flex items-center gap-2">
                <i class="pi pi-map-marker text-surface-400" aria-hidden="true" />{{ t('profile.teacher_page.where') }}
              </h2>
              <ul class="divide-y divide-surface-100 dark:divide-surface-800">
                <li v-for="(loc, index) in profile.locations" :key="index" class="flex items-start gap-3 py-2.5">
                  <span class="w-9 h-9 shrink-0 rounded-lg bg-primary-50 dark:bg-primary-400/15 text-primary-700 dark:text-primary-300 flex items-center justify-center">
                    <i :class="getLocationIcon(loc.type)" aria-hidden="true" />
                  </span>
                  <div class="min-w-0">
                    <p class="font-medium text-surface-900 dark:text-surface-0">{{ getLocationTypeLabel(loc.type) }}</p>
                    <p v-if="loc.city || loc.radius" class="text-sm text-surface-500 dark:text-surface-400">
                      {{ [loc.city, loc.type === 'student_place' && loc.radius ? t('profile.teacher.radius_km', { radius: loc.radius }) : null].filter(Boolean).join(', ') }}
                    </p>
                  </div>
                </li>
              </ul>
            </section>

            <section v-if="profile.availability?.length" class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-5">
              <h2 class="font-semibold text-surface-900 dark:text-surface-0 mb-3 flex items-center gap-2">
                <i class="pi pi-clock text-surface-400" aria-hidden="true" />{{ t('profile.teacher_page.when') }}
              </h2>
              <ul class="grid grid-cols-7 gap-1.5 text-center">
                <li v-for="day in week" :key="day.day" class="flex flex-col gap-1">
                  <span class="text-xs font-semibold text-surface-500 dark:text-surface-400">
                    <span aria-hidden="true">{{ getDayOfWeekLabel(day.day).slice(0, 3) }}</span>
                    <span class="sr-only">{{ getDayOfWeekLabel(day.day) }}</span>
                  </span>
                  <span
                    class="min-h-12 rounded-lg flex flex-col items-center justify-center gap-1 text-[11px] leading-tight font-semibold"
                    :class="day.slots.length ? 'bg-green-50 dark:bg-green-400/10 text-green-700 dark:text-green-300' : 'bg-surface-100 dark:bg-surface-800'"
                  >
                    <span v-for="slot in day.slots" :key="slot.start">
                      <span aria-hidden="true">{{ slot.start }}<br>{{ slot.end }}</span>
                      <span class="sr-only">{{ t('profile.teacher_page.slot', { start: slot.start, end: slot.end }) }}</span>
                    </span>
                    <span v-if="!day.slots.length" class="sr-only">{{ t('profile.teacher_page.no_slot') }}</span>
                  </span>
                </li>
              </ul>
              <p class="mt-2 text-xs text-surface-500 dark:text-surface-400">{{ t('profile.teacher_page.when_hint', { name: profile.display_name }) }}</p>
            </section>
          </div>

          <!-- Songs worked on with students (#1312) -->
          <section v-if="profile.songs?.length" class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-5 md:p-6">
            <h2 class="font-semibold text-surface-900 dark:text-surface-0 mb-3 flex items-center gap-2">
              <i class="pi pi-list text-surface-400" aria-hidden="true" />{{ t('profile.teacher_page.songs') }}
            </h2>
            <ul class="grid gap-2 sm:grid-cols-2">
              <li
                v-for="(song, index) in profile.songs"
                :key="index"
                class="flex items-center gap-3 rounded-lg border border-surface-200 dark:border-surface-700 px-3 py-2"
              >
                <i class="pi pi-play-circle text-primary" aria-hidden="true" />
                <span class="min-w-0">
                  <span class="block font-semibold text-sm text-surface-900 dark:text-surface-0 truncate">{{ song.title }}</span>
                  <span v-if="song.artist" class="block text-xs text-surface-500 dark:text-surface-400 truncate">{{ song.artist }}</span>
                </span>
              </li>
            </ul>
          </section>

          <!-- How the lessons go -->
          <section v-if="profile.description" class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-5 md:p-6">
            <h2 class="font-semibold text-surface-900 dark:text-surface-0 mb-3 flex items-center gap-2">
              <i class="pi pi-align-left text-surface-400" aria-hidden="true" />{{ t('profile.teacher_page.how') }}
            </h2>
            <div class="text-surface-700 dark:text-surface-300 whitespace-pre-line">{{ profile.description }}</div>
          </section>

          <!-- Their courses on MusicAll -->
          <section v-if="courses.length" class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-5 md:p-6">
            <h2 class="font-semibold text-surface-900 dark:text-surface-0 mb-3 flex items-center gap-2">
              <i class="pi pi-book text-surface-400" aria-hidden="true" />{{ t('profile.teacher_page.courses_title') }}
            </h2>
            <ul class="flex flex-col gap-3">
              <li v-for="course in courses" :key="course.id">
                <router-link :to="{ name: 'app_course_show', params: { slug: course.slug } }" class="flex items-center gap-3 group">
                  <img v-if="course.cover" :src="course.cover" alt="" class="w-24 h-16 rounded-lg object-cover shrink-0" />
                  <span v-else class="w-24 h-16 rounded-lg shrink-0 bg-surface-100 dark:bg-surface-800 flex items-center justify-center">
                    <i class="pi pi-book text-surface-400" aria-hidden="true" />
                  </span>
                  <span class="min-w-0">
                    <span class="block font-semibold text-surface-900 dark:text-surface-0 group-hover:text-primary line-clamp-2">{{ course.title }}</span>
                    <span class="block text-sm text-surface-500 dark:text-surface-400">
                      {{ [course.sub_category?.title, formatDate(course.publication_datetime)].filter(Boolean).join(', ') }}
                    </span>
                  </span>
                </router-link>
              </li>
            </ul>
          </section>

          <!-- The other media; the owner manages them all here -->
          <TeacherMediaShowcase
            v-if="isOwnProfile || otherMedia.length"
            :is-own-profile="isOwnProfile"
            :media-items="isOwnProfile ? null : otherMedia"
            :owner-username="profile.username"
          />

          <section v-if="profile.social_links?.length" class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-5 md:p-6">
            <h2 class="font-semibold text-surface-900 dark:text-surface-0 mb-3 flex items-center gap-2">
              <i class="pi pi-share-alt text-surface-400" aria-hidden="true" />{{ t('profile.teacher_page.social_links') }}
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
          </section>
        </div>

        <!-- Prices and facts, in view while the visitor reads -->
        <aside class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-5 flex flex-col gap-4 lg:sticky lg:top-24">
          <div v-if="cheapest" class="flex items-baseline gap-2">
            <span class="text-3xl font-extrabold text-surface-900 dark:text-surface-0">{{ formatPrice(cheapest.price) }}</span>
            <span class="text-sm text-surface-500 dark:text-surface-400">{{ t('profile.teacher_page.per_duration', { duration: getSessionDurationLabel(cheapest.duration) }) }}</span>
          </div>
          <p
            v-if="profile.offers_trial"
            class="flex items-center gap-2 rounded-lg px-3 py-2.5 text-sm font-semibold bg-green-50 dark:bg-green-400/10 text-green-700 dark:text-green-300"
          >
            <i class="pi pi-gift" aria-hidden="true" />{{ trialText }}
          </p>
          <ul v-if="profile.pricing?.length > 1" class="divide-y divide-dashed divide-surface-200 dark:divide-surface-700">
            <li v-for="pricing in sortedPricing" :key="pricing.duration" class="flex justify-between py-2">
              <span class="text-surface-600 dark:text-surface-400">{{ getSessionDurationLabel(pricing.duration) }}</span>
              <span class="font-bold text-surface-900 dark:text-surface-0">{{ formatPrice(pricing.price) }}</span>
            </li>
          </ul>
          <ul v-if="profile.packages?.length" class="flex flex-col gap-2">
            <li v-for="pkg in profile.packages" :key="pkg.id" class="rounded-lg border border-surface-200 dark:border-surface-700 p-3">
              <div class="flex justify-between gap-3">
                <span class="font-semibold text-surface-900 dark:text-surface-0">{{ pkg.title }}</span>
                <span class="font-bold text-surface-900 dark:text-surface-0">{{ formatPrice(pkg.price) }}</span>
              </div>
              <p class="text-sm text-surface-500 dark:text-surface-400">
                {{ [pkg.sessions_count ? t('profile.teacher_page.sessions', pkg.sessions_count) : null, pkg.description].filter(Boolean).join(', ') }}
              </p>
            </li>
          </ul>
          <Button
            v-if="!isOwnProfile"
            :label="mainActionLabel"
            icon="pi pi-send"
            severity="info"
            class="w-full max-lg:hidden!"
            @click="openRequest"
          />
          <dl class="grid grid-cols-[6rem_minmax(0,1fr)] gap-x-3 gap-y-3 text-sm border-t border-surface-200 dark:border-surface-700 pt-4">
            <template v-if="profile.student_levels?.length">
              <dt class="text-surface-500 dark:text-surface-400">{{ t('profile.teacher_page.student_levels') }}</dt>
              <dd class="flex flex-wrap gap-1.5">
                <Tag v-for="level in levels" :key="level" :value="getStudentLevelLabel(level)" severity="secondary" />
              </dd>
            </template>
            <template v-if="profile.age_groups?.length">
              <dt class="text-surface-500 dark:text-surface-400">{{ t('profile.teacher_page.age_groups') }}</dt>
              <dd class="flex flex-wrap gap-1.5">
                <Tag v-for="ageGroup in profile.age_groups" :key="ageGroup" :value="getAgeGroupLabel(ageGroup)" severity="secondary" />
              </dd>
            </template>
            <template v-if="profile.instruments?.length">
              <dt class="text-surface-500 dark:text-surface-400">{{ t('profile.teacher_page.instruments') }}</dt>
              <dd class="flex flex-wrap gap-1.5">
                <Tag v-for="instrument in profile.instruments" :key="instrument.instrument_id" :value="instrument.instrument_name" severity="info" />
              </dd>
            </template>
            <template v-if="profile.styles?.length">
              <dt class="text-surface-500 dark:text-surface-400">{{ t('profile.teacher_page.styles') }}</dt>
              <dd class="flex flex-wrap gap-1.5">
                <Tag v-for="style in profile.styles" :key="style.id" :value="style.name" severity="secondary" />
              </dd>
            </template>
          </dl>
        </aside>
      </div>

      <!-- Phone: the price and the main action stay at hand -->
      <div
        v-if="!isOwnProfile"
        class="md:hidden fixed inset-x-0 bottom-0 z-30 flex items-center gap-3 px-4 pt-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))] bg-surface-0/95 dark:bg-surface-900/95 backdrop-blur border-t border-surface-200 dark:border-surface-700"
      >
        <div v-if="cheapest" class="leading-tight">
          <span class="block font-extrabold text-surface-900 dark:text-surface-0">{{ formatPrice(cheapest.price) }}</span>
          <span class="block text-xs text-surface-500 dark:text-surface-400">{{ getSessionDurationLabel(cheapest.duration) }}</span>
        </div>
        <Button :label="mainActionLabel" icon="pi pi-send" severity="info" class="flex-1" @click="openRequest" />
      </div>
    </div>

    <MediaPlayerDialog v-model:visible="showPlayer" :media="playingMedia" />

    <!-- Edit modal -->
    <EditTeacherProfileModal
      v-model:visible="showEditModal"
      :teacher-profile="profile"
      @saved="handleProfileSaved"
    />

    <TrialRequestDialog v-if="profile && !isOwnProfile" v-model:visible="showRequestDialog" :profile="profile" />

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
import courseApi from '../../../api/course/course.js'
import AuthRequiredModal from '../../../components/Auth/AuthRequiredModal.vue'
import MediaPlayerDialog from '../../../components/Media/MediaPlayerDialog.vue'
import SendMessageModal from '../../../components/Message/SendMessageModal.vue'
import ShareButton from '../../../components/ShareButton.vue'
import EditTeacherProfileModal from '../../../components/Teacher/EditTeacherProfileModal.vue'
import TeacherMediaShowcase from '../../../components/Teacher/TeacherMediaShowcase.vue'
import TrialRequestDialog from '../../../components/Teacher/TrialRequestDialog.vue'
import PrivateProfileNotice from '../../../components/User/Profile/PrivateProfileNotice.vue'
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
import { currentBandsSummary } from '../../../utils/musicianBands.js'
import { isPrivateProfileError } from '../../../utils/profileError.js'
import {
  availabilityByDay,
  cheapestPricing,
  levelRange,
  orderedLevels,
  placesSummary,
  pricingByDuration
} from '../../../utils/teacherPage.js'
import { hasRequestDraft, openRequestDraftStorage } from '../../../utils/trialRequest.js'

const route = useRoute()
const router = useRouter()
const teacherProfileStore = useTeacherProfileStore()
const userSecurityStore = useUserSecurityStore()
const { t, locale } = useI18n()

const SKILL_STEPS = { beginner: 1, intermediate: 2, advanced: 3, professional: 4 }
// The same colours as the mock-up: green to start, blue in the middle, amber at the top.
// One column per level filled in, so two goals do not leave an empty third column.
const GOAL_COLUMNS = { 2: 'md:grid-cols-2', 3: 'md:grid-cols-3' }
const LEVEL_SEVERITY = { beginner: 'success', intermediate: 'info', advanced: 'warn' }
const COURSES_SHOWN = 3

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
const isPrivate = ref(false)
const showEditModal = ref(false)
const showMessageModal = ref(false)
const showAuthModal = ref(false)
const contactRecipient = ref(null)
const showPlayer = ref(false)
const showRequestDialog = ref(false)
const playingMedia = ref(null)
const courses = ref([])
const coursesTotal = ref(0)

const profile = computed(() => teacherProfileStore.profile)
const cameFromProfile = computed(() => route.query.from === 'profile')

const isOwnProfile = computed(() => {
  if (!profile.value || !userSecurityStore.userProfile) return false
  return userSecurityStore.userProfile.id === profile.value.user_id
})

const instrumentNames = computed(
  () => profile.value?.instruments?.map((instrument) => instrument.instrument_name) ?? []
)
const firstInstrument = computed(() => instrumentNames.value[0] ?? null)
const places = computed(() => placesSummary(profile.value?.locations))
const cheapest = computed(() => cheapestPricing(profile.value?.pricing))
const sortedPricing = computed(() => pricingByDuration(profile.value?.pricing))
const levels = computed(() => orderedLevels(profile.value?.student_levels))
const levelGoals = computed(() => profile.value?.level_goals ?? [])
const week = computed(() => availabilityByDay(profile.value?.availability))
const heroMedia = computed(() => profile.value?.media?.[0] ?? null)
const otherMedia = computed(() => profile.value?.media?.slice(1) ?? [])

function joinList(items, type) {
  return new Intl.ListFormat(locale.value, { type }).format(items)
}

/** « Ixelles, chez toi ou en ligne »: the teacher's city (or their place), the student's place, online. */
const placesLine = computed(() => {
  const parts = []
  if (places.value.city) parts.push(places.value.city)
  else if (places.value.atTeacher)
    parts.push(t('profile.teacher_page.at_teacher', { name: profile.value.display_name }))
  if (places.value.atStudent) parts.push(t('profile.teacher_page.at_student'))
  if (places.value.online) parts.push(t('profile.teacher_page.online'))
  return parts.length ? joinList(parts, 'disjunction') : ''
})

const ageGroupsLine = computed(() => {
  const groups = profile.value?.age_groups ?? []
  return groups.length
    ? joinList(
        groups.map((group) => getAgeGroupLabel(group)),
        'conjunction'
      )
    : ''
})

const trialText = computed(() =>
  profile.value?.trial_price
    ? t('profile.teacher_page.trial_priced', { price: formatPrice(profile.value.trial_price) })
    : t('profile.teacher_page.trial_free_banner')
)

const keyNumbers = computed(() => {
  const items = []
  if (cheapest.value) {
    items.push({
      key: 'price',
      value: formatPrice(cheapest.value.price),
      label: t('profile.teacher_page.per_duration', {
        duration: getSessionDurationLabel(cheapest.value.duration)
      })
    })
  }
  if (profile.value.offers_trial) {
    items.push({
      key: 'trial',
      value: profile.value.trial_price
        ? formatPrice(profile.value.trial_price)
        : t('profile.teacher_page.trial_offered'),
      label: t('profile.teacher_page.trial_lesson')
    })
  }
  const range = levelRange(profile.value.student_levels)
  if (range) {
    items.push({
      key: 'levels',
      value:
        range[0] === range[1]
          ? getStudentLevelLabel(range[0])
          : t('profile.teacher_page.level_range', {
              from: getStudentLevelLabel(range[0]),
              to: getStudentLevelLabel(range[1]).toLocaleLowerCase(locale.value)
            }),
      label: t('profile.teacher_page.levels')
    })
  }
  if (profile.value.years_of_experience) {
    items.push({
      key: 'years',
      value: t('profile.teacher_page.years', profile.value.years_of_experience),
      label: t('profile.teacher_page.of_teaching')
    })
  }
  return items
})

const mainActionLabel = computed(() =>
  profile.value?.offers_trial
    ? t('profile.teacher_page.ask_trial')
    : t('profile.teacher_page.ask_first')
)

// The bands they play in now lead « Le musicien derrière le prof » (#1313).
const bandSummary = computed(() => currentBandsSummary(profile.value?.musician_bands))

const musicianFacts = computed(() => {
  const facts = [
    t('profile.teacher_page.member_since', {
      year: new Date(profile.value.member_since).getFullYear()
    })
  ]
  if (coursesTotal.value) facts.push(t('profile.teacher_page.courses_count', coursesTotal.value))
  const text = facts.join(', ')
  // After « Joue dans … », the facts carry on the same sentence.
  return bandSummary.value ? text.charAt(0).toLocaleLowerCase(locale.value) + text.slice(1) : text
})

// The tab says what the search engines read (#1309).
const pageTitle = computed(() => {
  if (!profile.value) return t('profile.teacher_page.page_title_default')
  return t('profile.teacher_page.page_title', {
    name: profile.value.display_name,
    instruments: instrumentNames.value.length
      ? instrumentNames.value.join(', ').toLocaleLowerCase(locale.value)
      : t('profile.teacher_page.music')
  })
})

const shareUrl = computed(() => window.location.href)

const shareTitle = computed(() => {
  if (profile.value) {
    return t('profile.teacher_page.share_title', { username: profile.value.display_name })
  }
  return t('profile.teacher_page.share_title_default')
})

useTitle(pageTitle, { restoreOnUnmount: false })

// French as the profile always wrote it, whole euros and the sign glued on; English the Intl way,
// the cents shown only when there are some.
const PRICE_WRITERS = {
  fr: (cents) => `${(cents / 100).toFixed(0)}€`,
  en: formatEurosFromCentsTrimmed
}

function formatPrice(cents) {
  return forActiveLocale(PRICE_WRITERS)(cents)
}

function formatDate(value) {
  if (!value) return ''
  return new Date(value).toLocaleDateString(locale.value, {
    day: 'numeric',
    month: 'short',
    year: 'numeric'
  })
}

function skillSteps(level) {
  return SKILL_STEPS[level] ?? 0
}

function openMedia(item) {
  playingMedia.value = item
  showPlayer.value = true
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
    case 'tiktok':
      return '#000000'
    default:
      return null
  }
}

/** Their published courses: not essential, so a failure leaves the section out. */
async function loadCourses(username) {
  try {
    const data = await courseApi.getMemberCourses(username)
    // The visitor may have moved on to another teacher while this was loading.
    if (route.params.username !== username) return
    courses.value = (data.member ?? []).slice(0, COURSES_SHOWN)
    coursesTotal.value = data.totalItems ?? 0
  } catch {
    courses.value = []
    coursesTotal.value = 0
  }
}

async function loadProfile() {
  isLoading.value = true
  notFound.value = false
  isPrivate.value = false
  courses.value = []
  coursesTotal.value = 0

  try {
    await teacherProfileStore.loadPublicProfile(route.params.username)
    loadCourses(route.params.username)
  } catch (error) {
    isPrivate.value = isPrivateProfileError(error)
    notFound.value = !isPrivate.value
  } finally {
    isLoading.value = false
  }
}

function handleProfileSaved() {
  loadProfile()
}

function openRequest() {
  showRequestDialog.value = true
}

/**
 * A request written before signing in comes back once the visitor is signed in (#1311). Watched rather than called
 * after loading: on a full page load (an OAuth return) the profile and the sign-in state arrive in either order.
 */
watch(
  () => [profile.value, userSecurityStore.isAuthenticated, userSecurityStore.userProfile],
  () => reopenPendingRequest()
)

function reopenPendingRequest() {
  if (!profile.value || !userSecurityStore.userProfile || showRequestDialog.value) return
  if (
    !isOwnProfile.value &&
    userSecurityStore.isAuthenticated &&
    hasRequestDraft(openRequestDraftStorage(), route.params.username)
  ) {
    showRequestDialog.value = true
  }
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
