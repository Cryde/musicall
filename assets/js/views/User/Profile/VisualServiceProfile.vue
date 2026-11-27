<template>
  <div class="py-6 md:py-10" :class="{ 'max-md:pb-28': page && !isOwnPage }">
    <ServicePageStates
      :is-loading="isLoading"
      :is-private="isPrivate"
      :not-found="notFound"
      :not-found-title="t('profile.service_page.not_found_title')"
      :not-found-help="t(config.text.notFoundHelp)"
      :username="route.params.username"
    >
      <div v-if="page" class="flex flex-col gap-5 md:gap-6">
        <ProfilePrivateBar v-if="isOwnPage && !page.is_public" @made-public="refreshPage" />
        <ProfileDraftBar v-if="isOwnPage && page.status === 'draft'" />
        <ServicePageTopBar
          :username="page.username"
          :came-from-profile="route.query.from === 'profile'"
          :back-to-profile-label="t('profile.teacher_page.back_to_profile', { name: page.display_name })"
          :breadcrumb-label="t('profile.teacher_page.breadcrumb')"
          :crumbs="crumbs"
          @back="handleBack"
        />

        <ServicePageHeader
          :page="page"
          :is-own-page="isOwnPage"
          :icon="`${SERVICE_KINDS[kind].icon} ${config.iconColor}`"
          :kind-label="t(SERVICE_KINDS[kind].labelKey)"
          :specialities="specialities.map((speciality) => t(config.specialityKeys[speciality]))"
          :title="page.headline || t(config.text.titleDefault, { name: page.display_name })"
          :facts="facts"
          :key-numbers="keyNumbers"
          :hero-media="heroMedia"
          :hero-label="t('profile.visual_service_page.watch', { name: page.display_name })"
          :share-title="t(config.text.shareTitle, { name: page.display_name })"
          @edit="showForm = true"
          @quote="showQuoteDialog = true"
          @question="openMessage('')"
          @play="openMedia"
        />

        <div class="grid gap-5 md:gap-6 lg:grid-cols-[minmax(0,1fr)_340px] items-start">
          <div class="flex flex-col gap-5 md:gap-6 min-w-0">
            <!-- « Réalisations » first (#1411): a gallery of pictures, sites (#1412), or films with their credits -->
            <template v-if="page.works.length">
              <ServiceWorksGallery
                v-if="config.worksLayout === 'gallery'"
                :title="t('profile.visual_service_page.works_gallery')"
                :works="page.works"
                :username="page.username"
                :reportable="!isOwnPage"
                @play="openMedia"
              />
              <ServiceWorksSites
                v-else-if="config.worksLayout === 'sites'"
                :title="t('profile.visual_service_page.works_sites')"
                :works="page.works"
                :username="page.username"
                :reportable="!isOwnPage"
                @play="openMedia"
              />
              <ServiceWorksCredits
                v-else
                :title="t('profile.visual_service_page.works_credits', { name: page.display_name })"
                :works="page.works"
                :username="page.username"
                :reportable="!isOwnPage"
                :play-label="t('profile.service_works.watch')"
                :icon="SERVICE_KINDS[kind].icon"
                @play="openMedia"
              />
            </template>

            <ServiceOffersCard v-if="page.offers.length" :offers="page.offers" :unit-keys="VISUAL_UNIT_KEYS" />

            <ServiceTextCard v-if="page.description" :title="t('profile.service_page.how')" :text="page.description" />

            <ServicePlacesCard v-if="page.places.length" :title="t('profile.teacher_page.where')" :places="placeLines(page.places, { placeKeys: VISUAL_PLACE_KEYS }, t)" />

            <ServiceMediaCard v-if="otherMedia.length" :media="otherMedia" @play="openMedia" />
          </div>

          <div class="flex flex-col gap-5 md:gap-6 lg:sticky lg:top-24">
            <ServiceContactCard
              :page="page"
              :is-own-page="isOwnPage"
              :facts="contactFacts"
              @edit="showForm = true"
              @quote="showQuoteDialog = true"
              @question="openMessage('')"
            />
            <ServiceWhoCard :page="page" />
          </div>
        </div>

        <ServicePhoneBar v-if="!isOwnPage">
          <div v-if="lead" class="leading-tight">
            <span class="block font-extrabold text-surface-900 dark:text-surface-0">{{ formatLessonPrice(lead.price) }}</span>
            <span class="block text-xs text-surface-500 dark:text-surface-400">{{ t(VISUAL_UNIT_KEYS[lead.unit]) }}</span>
          </div>
          <Button :label="t('profile.service_page.ask_quote')" icon="pi pi-send" severity="info" class="flex-1" @click="showQuoteDialog = true" />
        </ServicePhoneBar>
      </div>
    </ServicePageStates>

    <MediaPlayerDialog v-model:visible="showPlayer" :media="playingMedia" />

    <EditVisualServiceDialog v-if="page && isOwnPage" v-model:visible="showForm" :kind="kind" :service-id="page.id" @saved="refreshPage" @changed="refreshPage" />

    <SendMessageModal
      v-model:visible="showMessageModal"
      :selected-recipient="contactRecipient"
      :initial-content="initialMessage"
      :contact-origin="page ? { serviceId: page.id } : null"
      @sign-in-required="handleContactSignInRequired"
    />
    <QuoteRequestDialog v-if="page && !isOwnPage" v-model:visible="showQuoteDialog" :page="page" :kind="kind" />
    <AuthRequiredModal v-model:visible="showAuthModal" :message="authModalMessage" />
  </div>
</template>

<script setup>
import { useTitle } from '@vueuse/core'
import Button from 'primevue/button'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import visualServiceApi from '../../../api/service/visualService.js'
import AuthRequiredModal from '../../../components/Auth/AuthRequiredModal.vue'
import MediaPlayerDialog from '../../../components/Media/MediaPlayerDialog.vue'
import SendMessageModal from '../../../components/Message/SendMessageModal.vue'
import ServiceContactCard from '../../../components/Service/Page/ServiceContactCard.vue'
import ServiceMediaCard from '../../../components/Service/Page/ServiceMediaCard.vue'
import ServiceOffersCard from '../../../components/Service/Page/ServiceOffersCard.vue'
import ServicePageHeader from '../../../components/Service/Page/ServicePageHeader.vue'
import ServicePageStates from '../../../components/Service/Page/ServicePageStates.vue'
import ServicePageTopBar from '../../../components/Service/Page/ServicePageTopBar.vue'
import ServicePhoneBar from '../../../components/Service/Page/ServicePhoneBar.vue'
import ServicePlacesCard from '../../../components/Service/Page/ServicePlacesCard.vue'
import ServiceTextCard from '../../../components/Service/Page/ServiceTextCard.vue'
import ServiceWhoCard from '../../../components/Service/Page/ServiceWhoCard.vue'
import QuoteRequestDialog from '../../../components/Service/QuoteRequestDialog.vue'
import EditVisualServiceDialog from '../../../components/Service/Visual/EditVisualServiceDialog.vue'
import ServiceWorksCredits from '../../../components/Service/Works/ServiceWorksCredits.vue'
import ServiceWorksGallery from '../../../components/Service/Works/ServiceWorksGallery.vue'
import ServiceWorksSites from '../../../components/Service/Works/ServiceWorksSites.vue'
import ProfileDraftBar from '../../../components/User/Profile/ProfileDraftBar.vue'
import ProfilePrivateBar from '../../../components/User/Profile/ProfilePrivateBar.vue'
import { useServicePage } from '../../../composables/useServicePage.js'
import { useServicePageBack } from '../../../composables/useServicePageBack.js'
import { SERVICE_KINDS } from '../../../constants/serviceKinds.js'
import {
  VISUAL_PLACE_KEYS,
  VISUAL_SERVICE_KINDS,
  VISUAL_UNIT_KEYS
} from '../../../constants/visualServices.js'
import { orderedSpecialities } from '../../../utils/servicePage.js'
import { placeLines, placesSummary } from '../../../utils/servicePlaces.js'
import { cheapestPricing, formatLessonPrice } from '../../../utils/teacherPage.js'
import { deliveryLine, kindSpecialities } from '../../../utils/visualServicePage.js'

/** The page of a kind shown by its works (#1411, #1412), on the template the other service pages share. */
const props = defineProps({
  /** A ServiceKind value of VISUAL_SERVICE_KINDS, from the route. */
  kind: { type: String, required: true }
})

const route = useRoute()
const { t } = useI18n()
const handleBack = useServicePageBack()
const {
  page,
  isLoading,
  notFound,
  isPrivate,
  isOwnPage,
  showForm,
  showPlayer,
  playingMedia,
  showMessageModal,
  showQuoteDialog,
  showAuthModal,
  authModalMessage,
  contactRecipient,
  initialMessage,
  refreshPage,
  openMedia,
  openMessage,
  handleContactSignInRequired
} = useServicePage({
  kind: () => props.kind,
  fetchPage: (username) => visualServiceApi.getPage(props.kind, username)
})

const config = computed(() => VISUAL_SERVICE_KINDS[props.kind])
const specialities = computed(() =>
  orderedSpecialities(kindSpecialities(props.kind), page.value?.specialities)
)
const places = computed(() => placesSummary(page.value?.places))
// The cheapest of any unit, as Service::leadOffer() picks it for these kinds.
const lead = computed(() => cheapestPricing(page.value?.offers))
const heroMedia = computed(() => page.value?.media?.[0] ?? null)
const otherMedia = computed(() => page.value?.media?.slice(1) ?? [])

const crumbs = computed(() => [
  { label: t(config.value.text.plural), to: { name: SERVICE_KINDS[props.kind].search.route } },
  ...(specialities.value.length
    ? [{ label: t(config.value.specialityKeys[specialities.value[0]]) }]
    : []),
  ...(places.value.city ? [{ label: places.value.city, current: true }] : [])
])

/** « Studio à Lyon », « À distance », « Jusqu'à 100 km », « 10 ans d'expérience ». */
const facts = computed(() => {
  const items = []
  if (places.value.studioCity) {
    items.push({
      key: 'studio',
      icon: 'pi pi-map-marker',
      text: t('profile.service_page.studio_in', { city: places.value.studioCity })
    })
  }
  if (places.value.remote) {
    items.push({
      key: 'remote',
      icon: 'pi pi-globe',
      text: t(VISUAL_PLACE_KEYS.remote)
    })
  }
  if (places.value.travels) {
    items.push({
      key: 'travel',
      icon: 'pi pi-car',
      text: places.value.travelRadius
        ? t('profile.service_page.travels_up_to', { radius: places.value.travelRadius })
        : t('profile.service_page.travels')
    })
  }
  if (page.value.years_of_experience) {
    items.push({
      key: 'years',
      icon: 'pi pi-star',
      text: t('profile.visual_service_page.years', page.value.years_of_experience)
    })
  }
  return items
})

const keyNumbers = computed(() => {
  const items = []
  if (lead.value) {
    items.push({
      key: 'price',
      value: formatLessonPrice(lead.value.price),
      label: t(VISUAL_UNIT_KEYS[lead.value.unit])
    })
  }
  if (page.value.delivery_days) {
    items.push({
      key: 'delivery',
      value: t('profile.service_page.days', page.value.delivery_days),
      label: t('profile.visual_service_page.delivery_time')
    })
  }
  return items
})

const contactFacts = computed(() => {
  const delivery = deliveryLine(page.value, t)
  return [
    ...(page.value.styles.length
      ? [
          {
            key: 'styles',
            label: t('profile.teacher_page.styles'),
            tags: page.value.styles.map((style) => style.name)
          }
        ]
      : []),
    ...(delivery
      ? [{ key: 'delivery', label: t('profile.visual_service_page.delivery'), text: delivery }]
      : [])
  ]
})

useTitle(
  computed(() =>
    page.value
      ? t(config.value.text.pageTitle, { name: page.value.display_name })
      : t(config.value.text.pageTitleDefault)
  ),
  { restoreOnUnmount: false }
)
</script>
