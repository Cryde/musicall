<template>
  <div class="max-w-6xl w-full mx-auto md:py-6 lg:py-12 flex flex-col gap-10 md:gap-24">
    <section class="flex flex-col gap-7 md:gap-10">
      <div class="flex flex-col gap-2.5 md:gap-4 max-w-3xl">
        <span class="text-[13px] md:text-sm font-bold tracking-wider uppercase text-primary">{{ t('home.band_space_presentation.eyebrow') }}</span>
        <h1 class="m-0 text-[26px] leading-[1.15] md:text-4xl md:leading-tight lg:text-5xl font-extrabold text-surface-900 dark:text-surface-0">
          {{ t('home.band_space_presentation.title') }}
        </h1>
        <p class="m-0 text-base leading-[1.55] md:text-lg md:leading-relaxed text-surface-600 dark:text-surface-300">
          {{ t('home.band_space_presentation.intro') }}
        </p>
      </div>

      <!-- Below lg the demo comes straight after the questions, so each answer shows under the thumb, and the
           create button after the demo it creates. -->
      <div class="grid lg:grid-cols-[26rem_minmax(0,1fr)] gap-4 lg:gap-8 items-start">
        <!-- No submit button: Enter in the name, the form's only text field, still submits it. -->
        <form
          class="flex flex-col gap-5 p-5 rounded-[20px] md:gap-7 md:p-6 md:rounded-2xl lg:p-8 bg-surface-0 dark:bg-surface-900"
          @submit.prevent="handleCreate"
        >
          <div class="flex flex-col gap-2 md:gap-2.5">
            <label for="demo-band-name" :class="QUESTION">{{ t('home.band_space_presentation.form.name_question') }}</label>
            <InputText
              id="demo-band-name"
              v-model="bandName"
              :placeholder="t('home.band_space_presentation.form.name_placeholder')"
              maxlength="40"
              size="large"
              class="w-full"
            />
          </div>

          <fieldset class="m-0 p-0 border-0 flex flex-col gap-2 md:gap-2.5">
            <legend :class="[QUESTION, 'mb-2 md:mb-2.5']">{{ t('home.band_space_presentation.form.size_question') }}</legend>
            <div class="grid grid-cols-5 gap-1.5 md:flex md:flex-wrap md:gap-2">
              <button
                v-for="size in DEMO_SIZES"
                :key="size"
                type="button"
                :aria-pressed="size === bandSize"
                :class="[PILL, SIZE_PILL, size === bandSize ? PILL_ON : PILL_OFF]"
                @click="bandSize = size"
              >
                {{ sizeLabel(size) }}
              </button>
            </div>
          </fieldset>

          <fieldset class="m-0 p-0 border-0 flex flex-col gap-2 md:gap-2.5">
            <legend :class="[QUESTION, 'mb-1']">{{ t('home.band_space_presentation.form.pains_question') }}</legend>
            <span class="text-sm text-surface-600 dark:text-surface-400">{{ t('home.band_space_presentation.form.pains_hint') }}</span>
            <div class="flex flex-wrap gap-2">
              <button
                v-for="pain in DEMO_PAINS"
                :key="pain.key"
                type="button"
                :aria-pressed="pickedPains.includes(pain.key)"
                :class="[PILL, PAIN_PILL, pickedPains.includes(pain.key) ? PILL_ON : PILL_OFF]"
                @click="togglePain(pain.key)"
              >
                <i v-if="pickedPains.includes(pain.key)" class="pi pi-check !text-xs md:!hidden" aria-hidden="true" />
                {{ pain.label }}
              </button>
            </div>
          </fieldset>

          <BandSpaceCreateCall class="max-lg:hidden" :label="ctaLabel(bandName)" @create="handleCreate" />
        </form>

        <BandSpaceDemoPreview
          :name="bandName"
          :size="bandSize"
          :picked-pains="pickedPains"
          :requested-tab="route.query.module"
        />

        <BandSpaceCreateCall class="mt-2 lg:hidden" :label="ctaLabel(bandName)" @create="handleCreate" />
      </div>
    </section>

    <!-- One card of three rows below lg, three cards beside each other from lg. -->
    <section
      class="grid lg:grid-cols-3 lg:gap-5 max-lg:overflow-hidden max-lg:rounded-[20px] max-lg:bg-surface-0 max-lg:dark:bg-surface-900 max-lg:divide-y max-lg:divide-surface-200 max-lg:dark:divide-surface-700"
      :aria-label="t('home.band_space_presentation.reasons_aria_label')"
    >
      <div
        v-for="reason in reasons"
        :key="reason.title"
        class="grid grid-cols-[auto_minmax(0,1fr)] gap-x-3.5 gap-y-0.5 px-[18px] py-4 lg:flex lg:flex-col lg:gap-3 lg:p-7 lg:rounded-2xl lg:bg-surface-0 lg:dark:bg-surface-900"
      >
        <i :class="[reason.icon, 'row-span-2 mt-[3px] lg:mt-0 !text-lg lg:!text-2xl', reason.accent]" aria-hidden="true" />
        <h2 class="m-0 text-base lg:text-lg font-bold text-surface-900 dark:text-surface-0">{{ reason.title }}</h2>
        <p class="m-0 text-[14.5px] leading-normal lg:text-base lg:leading-relaxed text-surface-600 dark:text-surface-300">{{ reason.body }}</p>
      </div>
    </section>

    <BandSpaceMobileApp />

    <section class="flex flex-col md:flex-row md:items-center justify-between gap-4 px-5 py-[22px] rounded-[20px] md:gap-8 md:p-8 md:rounded-3xl lg:p-14 bg-surface-0 dark:bg-surface-900">
      <div class="flex flex-col gap-2.5">
        <h2 class="m-0 text-[22px]/[1.25] md:text-3xl font-extrabold text-surface-900 dark:text-surface-0">{{ closingTitle }}</h2>
        <p class="m-0 text-[15px]/[1.55] md:text-lg text-surface-600 dark:text-surface-300">
          {{ t('home.band_space_presentation.closing.body') }}
        </p>
      </div>
      <Button :label="ctaLabel(bandName)" icon="pi pi-plus" size="large" class="shrink-0 max-md:w-full" @click="handleCreate" />
    </section>
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import BandSpaceCreateCall from '../../components/BandSpace/Presentation/BandSpaceCreateCall.vue'
import BandSpaceDemoPreview from '../../components/BandSpace/Presentation/BandSpaceDemoPreview.vue'
import BandSpaceMobileApp from '../../components/BandSpace/Presentation/BandSpaceMobileApp.vue'
import { useBandSpaceStore } from '../../store/bandSpace/bandSpace.js'
import { useUserSecurityStore } from '../../store/user/security.js'
import { ctaLabel, DEMO_PAINS, DEMO_SIZES, sizeLabel } from '../../utils/bandSpaceDemo.js'
import { openDraftNameStorage, saveDraftName } from '../../utils/bandSpaceDraftName.js'
import { bandSpaceCreateReturnPath, routeReturningTo } from '../../utils/unauthenticatedRedirect.js'

const QUESTION = 'text-[13px] md:text-sm font-bold tracking-wider uppercase text-primary'
const PILL = 'rounded-lg border font-semibold cursor-pointer transition-colors'
// On a phone the five sizes share one row, so « 6+ » never wraps alone.
const SIZE_PILL = 'h-11 text-[15px] md:min-w-14 md:px-4'
const PAIN_PILL =
  'inline-flex items-center gap-2 h-10 px-3 text-sm md:h-11 md:min-w-14 md:px-4 md:text-[15px]'
const PILL_ON = 'bg-primary border-primary text-primary-contrast'
const PILL_OFF =
  'bg-transparent border-surface-300 dark:border-surface-600 text-surface-700 dark:text-surface-200 hover:border-primary'

const { t } = useI18n()

const reasons = computed(() => [
  {
    icon: 'pi pi-envelope',
    accent: 'text-primary',
    title: t('home.band_space_presentation.reasons.invite_title'),
    body: t('home.band_space_presentation.reasons.invite_body')
  },
  {
    icon: 'pi pi-sync',
    accent: 'text-fuchsia-700 dark:text-fuchsia-300',
    title: t('home.band_space_presentation.reasons.several_bands_title'),
    body: t('home.band_space_presentation.reasons.several_bands_body')
  },
  {
    icon: 'pi pi-lock',
    accent: 'text-teal-700 dark:text-teal-300',
    title: t('home.band_space_presentation.reasons.private_title'),
    body: t('home.band_space_presentation.reasons.private_body')
  }
])

const route = useRoute()
const router = useRouter()
const bandSpaceStore = useBandSpaceStore()
const userSecurityStore = useUserSecurityStore()

const bandName = ref('')
const bandSize = ref(4)
const pickedPains = ref(['agenda', 'finances'])

const closingTitle = computed(() => {
  const name = bandName.value.trim()
  return name
    ? t('home.band_space_presentation.closing.title_named', { name })
    : t('home.band_space_presentation.closing.title_default')
})

function togglePain(key) {
  pickedPains.value = pickedPains.value.includes(key)
    ? pickedPains.value.filter((picked) => picked !== key)
    : [...pickedPains.value, key]
}

// A visitor signs up first and finds the name waiting in the create modal afterwards. A member goes
// straight to the band layout, which mounts the modal already open.
function handleCreate() {
  saveDraftName(openDraftNameStorage(), bandName.value)

  if (!userSecurityStore.isAuthenticated) {
    const bandIndexPath = router.resolve({ name: 'app_band_index' }).href
    router.push(routeReturningTo('app_register', bandSpaceCreateReturnPath(route, bandIndexPath)))
    return
  }

  bandSpaceStore.openCreateModal()
  router.push({ name: 'app_band_index' })
}
</script>
