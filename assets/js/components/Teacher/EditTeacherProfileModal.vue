<template>
  <Dialog
    v-model:visible="isVisible"
    modal
    header="Modifier le profil professeur"
    :style="{ width: '60rem' }"
    :breakpoints="{ '1280px': '90vw', '768px': '95vw' }"
    :closable="!isSaving"
    :closeOnEscape="!isSaving"
  >
    <Message v-if="error" severity="error" :closable="false" class="mb-4">
      {{ error }}
    </Message>

    <div class="flex flex-col gap-8">
      <!-- ═══════════════════════════════════════════════════════════════════
           SECTION 1: Identity & Expertise (Who I am)
           ═══════════════════════════════════════════════════════════════════ -->
      <div class="flex flex-col gap-5">
        <div class="flex items-center gap-2 pb-2 border-b border-surface-200 dark:border-surface-700">
          <i class="pi pi-user text-primary-500" />
          <h3 class="text-base font-semibold text-surface-900 dark:text-surface-0">Identité & Expertise</h3>
        </div>

        <!-- Instruments (required) -->
        <div class="flex flex-col gap-2">
          <label for="instruments" class="font-medium text-surface-900 dark:text-surface-0">
            Instruments enseignés
          </label>
          <MultiSelect
            id="instruments"
            v-model="selectedInstrumentIds"
            :options="instrumentOptions"
            optionLabel="name"
            optionValue="id"
            placeholder="Sélectionnez vos instruments"
            :disabled="isSaving"
            class="w-full"
            display="chip"
            filter
          />
        </div>

        <!-- Styles (optional) -->
        <div class="flex flex-col gap-2">
          <label for="styles" class="font-medium text-surface-900 dark:text-surface-0">
            Styles enseignés
            <span class="font-normal text-surface-500 dark:text-surface-400">(optionnel)</span>
          </label>
          <MultiSelect
            id="styles"
            v-model="selectedStyleIds"
            :options="styleOptions"
            optionLabel="name"
            optionValue="id"
            placeholder="Sélectionnez vos styles"
            :disabled="isSaving"
            class="w-full"
            display="chip"
            filter
          />
        </div>

        <!-- Description (required) -->
        <div class="flex flex-col gap-2">
          <label for="description" class="font-medium text-surface-900 dark:text-surface-0">
            Présentation
          </label>
          <Textarea
            id="description"
            v-model="description"
            rows="4"
            placeholder="Présentez-vous, votre méthode d'enseignement, votre parcours..."
            :disabled="isSaving"
            class="w-full"
          />
        </div>

        <!-- Years of experience (required) -->
        <div class="flex flex-col gap-2">
          <label for="yearsOfExperience" class="font-medium text-surface-900 dark:text-surface-0">
            Années d'expérience
          </label>
          <InputNumber
            id="yearsOfExperience"
            v-model="yearsOfExperience"
            :min="0"
            :max="70"
            placeholder="Ex: 5"
            :disabled="isSaving"
            class="w-48"
          />
        </div>

        <!-- Course title (optional) -->
        <div class="flex flex-col gap-2">
          <label for="courseTitle" class="font-medium text-surface-900 dark:text-surface-0">
            Titre du cours / Spécialisation
            <span class="font-normal text-surface-500 dark:text-surface-400">(optionnel)</span>
          </label>
          <InputText
            id="courseTitle"
            v-model="courseTitle"
            placeholder="Ex: Cours de guitare jazz pour débutants"
            :disabled="isSaving"
            class="w-full"
          />
        </div>
      </div>

      <!-- ═══════════════════════════════════════════════════════════════════
           SECTION 2: Target Audience (For whom)
           ═══════════════════════════════════════════════════════════════════ -->
      <div class="flex flex-col gap-5">
        <div class="flex items-center gap-2 pb-2 border-b border-surface-200 dark:border-surface-700">
          <i class="pi pi-users text-primary-500" />
          <h3 class="text-base font-semibold text-surface-900 dark:text-surface-0">Public cible</h3>
        </div>

        <!-- Student levels (optional) -->
        <div class="flex flex-col gap-2">
          <label for="studentLevels" class="font-medium text-surface-900 dark:text-surface-0">
            Niveaux acceptés
            <span class="font-normal text-surface-500 dark:text-surface-400">(optionnel)</span>
          </label>
          <MultiSelect
            id="studentLevels"
            v-model="studentLevels"
            :options="studentLevelOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="Sélectionnez les niveaux"
            :disabled="isSaving"
            class="w-full"
            display="chip"
          />
        </div>

        <!-- Age groups (optional) -->
        <div class="flex flex-col gap-2">
          <label for="ageGroups" class="font-medium text-surface-900 dark:text-surface-0">
            Tranches d'âge acceptées
            <span class="font-normal text-surface-500 dark:text-surface-400">(optionnel)</span>
          </label>
          <MultiSelect
            id="ageGroups"
            v-model="ageGroups"
            :options="ageGroupOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="Sélectionnez les tranches d'âge"
            :disabled="isSaving"
            class="w-full"
            display="chip"
          />
        </div>
      </div>

      <!-- ═══════════════════════════════════════════════════════════════════
           SECTION 3: Organization (How)
           ═══════════════════════════════════════════════════════════════════ -->
      <div class="flex flex-col gap-5">
        <div class="flex items-center gap-2 pb-2 border-b border-surface-200 dark:border-surface-700">
          <i class="pi pi-calendar text-primary-500" />
          <h3 class="text-base font-semibold text-surface-900 dark:text-surface-0">Organisation</h3>
        </div>

        <!-- Locations (optional) -->
        <div class="flex flex-col gap-3">
          <label class="font-medium text-surface-900 dark:text-surface-0">
            Lieux d'enseignement
            <span class="font-normal text-surface-500 dark:text-surface-400">(optionnel)</span>
          </label>
          <div class="flex flex-col gap-2">
            <div
              v-for="(loc, index) in locations"
              :key="index"
              class="flex flex-col gap-2 p-3 bg-surface-50 dark:bg-surface-800 rounded-lg"
            >
              <div class="flex items-center gap-2">
                <Select
                  v-model="loc.type"
                  :options="locationTypeOptions"
                  optionLabel="label"
                  optionValue="value"
                  placeholder="Type de lieu"
                  :disabled="isSaving"
                  class="w-48"
                />
                <Button
                  icon="pi pi-times"
                  severity="danger"
                  text
                  rounded
                  size="small"
                  :disabled="isSaving"
                  @click="removeLocation(index)"
                />
              </div>
              <div v-if="loc.type === 'teacher_place' || loc.type === 'student_place'" class="flex flex-col gap-2">
                <AutoComplete
                  v-model="loc.locationSearch"
                  :suggestions="loc.suggestions || []"
                  optionLabel="fullName"
                  placeholder="Rechercher une ville..."
                  :disabled="isSaving"
                  class="w-full"
                  @complete="(event) => searchLocation(event, index)"
                  @item-select="(event) => selectLocation(event, index)"
                />
                <div v-if="loc.city" class="text-sm text-surface-600 dark:text-surface-400">
                  <i class="pi pi-map-marker mr-1" />{{ loc.city }}<span v-if="loc.country">, {{ loc.country }}</span>
                </div>
                <InputText
                  v-if="loc.type === 'teacher_place'"
                  v-model="loc.address"
                  placeholder="Adresse (optionnel) - Ex: 18 rue de la Glacière"
                  :disabled="isSaving"
                  class="w-full"
                />
              </div>
              <div v-if="loc.type === 'student_place'" class="flex items-center gap-2">
                <InputNumber
                  v-model="loc.radius"
                  :min="1"
                  :max="200"
                  placeholder="Rayon"
                  suffix=" km"
                  :disabled="isSaving"
                  class="w-36"
                />
                <span class="text-sm text-surface-500 dark:text-surface-400">autour de la ville</span>
              </div>
            </div>
            <Button
              label="Ajouter un lieu"
              icon="pi pi-plus"
              severity="secondary"
              size="small"
              :disabled="isSaving"
              class="w-fit"
              @click="addLocation"
            />
          </div>
        </div>

        <!-- Availability (optional) -->
        <div class="flex flex-col gap-3">
          <label class="font-medium text-surface-900 dark:text-surface-0">
            Disponibilités
            <span class="font-normal text-surface-500 dark:text-surface-400">(optionnel)</span>
          </label>
          <div class="flex flex-col gap-2">
            <div
              v-for="(slot, index) in availabilitySlots"
              :key="index"
              class="flex items-center gap-2 p-2 bg-surface-50 dark:bg-surface-800 rounded-lg"
            >
              <Select
                v-model="slot.dayOfWeek"
                :options="dayOfWeekOptions"
                optionLabel="label"
                optionValue="value"
                placeholder="Jour"
                :disabled="isSaving"
                class="w-36"
              />
              <InputText
                v-model="slot.startTime"
                placeholder="09:00"
                :disabled="isSaving"
                class="w-24"
              />
              <span class="text-surface-500">à</span>
              <InputText
                v-model="slot.endTime"
                placeholder="18:00"
                :disabled="isSaving"
                class="w-24"
              />
              <Button
                icon="pi pi-times"
                severity="danger"
                text
                rounded
                size="small"
                :disabled="isSaving"
                @click="removeAvailabilitySlot(index)"
              />
            </div>
            <Button
              label="Ajouter un créneau"
              icon="pi pi-plus"
              severity="secondary"
              size="small"
              :disabled="isSaving"
              class="w-fit"
              @click="addAvailabilitySlot"
            />
          </div>
        </div>

        <!-- Trial lesson (optional) -->
        <div class="flex flex-col gap-3">
          <div class="flex items-center gap-3">
            <Checkbox
              id="offersTrial"
              v-model="offersTrial"
              binary
              :disabled="isSaving"
            />
            <label for="offersTrial" class="font-medium text-surface-900 dark:text-surface-0">
              Je propose un cours d'essai
              <span class="font-normal text-surface-500 dark:text-surface-400">(optionnel)</span>
            </label>
          </div>
          <div v-if="offersTrial" class="flex flex-col gap-2 ml-8">
            <label for="trialPrice" class="text-sm text-surface-600 dark:text-surface-400">
              Prix du cours d'essai
            </label>
            <InputNumber
              id="trialPrice"
              v-model="trialPriceDisplay"
              :min="0"
              :max="200"
              placeholder="Ex: 20 (ou 0 pour gratuit)"
              suffix="€"
              :disabled="isSaving"
              class="w-48"
            />
          </div>
        </div>
      </div>

      <!-- ═══════════════════════════════════════════════════════════════════
           SECTION 4: Pricing (How much)
           ═══════════════════════════════════════════════════════════════════ -->
      <div class="flex flex-col gap-5">
        <div class="flex items-center gap-2 pb-2 border-b border-surface-200 dark:border-surface-700">
          <i class="pi pi-euro text-primary-500" />
          <h3 class="text-base font-semibold text-surface-900 dark:text-surface-0">Tarifs</h3>
        </div>

        <!-- Pricing per duration (optional) -->
        <div class="flex flex-col gap-3">
          <label class="font-medium text-surface-900 dark:text-surface-0">
            Tarifs par durée de cours
            <span class="font-normal text-surface-500 dark:text-surface-400">(optionnel)</span>
          </label>
          <MultiSelect
            v-model="selectedDurations"
            :options="durationOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="Sélectionnez les durées proposées"
            :disabled="isSaving"
            class="w-full"
            display="chip"
          />
          <div v-if="selectedDurations.length" class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-2">
            <div v-for="duration in selectedDurationOptions" :key="duration.value" class="flex flex-col gap-1">
              <label :for="`pricing-${duration.value}`" class="text-sm text-surface-600 dark:text-surface-400">
                {{ duration.label }}
              </label>
              <InputNumber
                :id="`pricing-${duration.value}`"
                v-model="pricingByDuration[duration.value]"
                :min="0"
                :max="500"
                placeholder="€"
                suffix="€"
                :disabled="isSaving"
                class="w-full"
              />
            </div>
          </div>
        </div>

        <!-- Packages (optional) -->
        <div class="flex flex-col gap-3">
          <label class="font-medium text-surface-900 dark:text-surface-0">
            Forfaits / Abonnements
            <span class="font-normal text-surface-500 dark:text-surface-400">(optionnel)</span>
          </label>
          <div class="flex flex-col gap-2">
            <div
              v-for="(pkg, index) in packages"
              :key="index"
              class="flex flex-col gap-2 p-3 bg-surface-50 dark:bg-surface-800 rounded-lg"
            >
              <div class="flex items-start gap-2">
                <div class="flex-1 flex flex-col gap-2">
                  <InputText
                    v-model="pkg.title"
                    placeholder="Titre du forfait - Ex: Forfait semestre"
                    :disabled="isSaving"
                    class="w-full"
                  />
                  <Textarea
                    v-model="pkg.description"
                    rows="2"
                    placeholder="Description (optionnel)"
                    :disabled="isSaving"
                    class="w-full"
                  />
                  <div class="flex items-center gap-3">
                    <div class="flex items-center gap-2">
                      <InputNumber
                        v-model="pkg.sessionsCount"
                        :min="1"
                        :max="100"
                        placeholder="Nb séances"
                        :disabled="isSaving"
                        class="w-32"
                      />
                      <span class="text-sm text-surface-500 dark:text-surface-400">séances</span>
                    </div>
                    <div class="flex items-center gap-2">
                      <InputNumber
                        v-model="pkg.price"
                        :min="0"
                        :max="10000"
                        placeholder="Prix"
                        suffix="€"
                        :disabled="isSaving"
                        class="w-32"
                      />
                    </div>
                  </div>
                </div>
                <Button
                  icon="pi pi-times"
                  severity="danger"
                  text
                  rounded
                  size="small"
                  :disabled="isSaving"
                  @click="removePackage(index)"
                />
              </div>
            </div>
            <Button
              label="Ajouter un forfait"
              icon="pi pi-plus"
              severity="secondary"
              size="small"
              :disabled="isSaving"
              class="w-fit"
              @click="addPackage"
            />
          </div>
        </div>
      </div>

      <!-- ═══════════════════════════════════════════════════════════════════
           SECTION 5: Social Links
           ═══════════════════════════════════════════════════════════════════ -->
      <div class="flex flex-col gap-5">
        <div class="flex items-center gap-2 pb-2 border-b border-surface-200 dark:border-surface-700">
          <i class="pi pi-share-alt text-primary-500" />
          <h3 class="text-base font-semibold text-surface-900 dark:text-surface-0">Réseaux sociaux</h3>
        </div>

        <div class="flex flex-col gap-3">
          <label class="font-medium text-surface-900 dark:text-surface-0">
            Liens vers vos réseaux
            <span class="font-normal text-surface-500 dark:text-surface-400">(optionnel)</span>
          </label>
          <div class="flex flex-col gap-2">
            <div
              v-for="(link, index) in socialLinks"
              :key="index"
              class="flex items-center gap-2 p-2 bg-surface-50 dark:bg-surface-800 rounded-lg"
            >
              <Select
                v-model="link.platform"
                :options="socialPlatformOptions"
                optionLabel="label"
                optionValue="value"
                placeholder="Plateforme"
                :disabled="isSaving"
                class="w-44"
              />
              <InputText
                v-model="link.url"
                placeholder="https://..."
                :disabled="isSaving"
                class="flex-1"
              />
              <Button
                icon="pi pi-times"
                severity="danger"
                text
                rounded
                size="small"
                :disabled="isSaving"
                @click="removeSocialLink(index)"
              />
            </div>
            <Button
              label="Ajouter un lien"
              icon="pi pi-plus"
              severity="secondary"
              size="small"
              :disabled="isSaving"
              class="w-fit"
              @click="addSocialLink"
            />
          </div>
        </div>
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end gap-2">
        <Button
          label="Annuler"
          severity="secondary"
          :disabled="isSaving"
          @click="handleClose"
        />
        <Button
          label="Enregistrer"
          icon="pi pi-check"
          :loading="isSaving"
          @click="handleSave"
        />
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import AutoComplete from 'primevue/autocomplete'
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import Dialog from 'primevue/dialog'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import MultiSelect from 'primevue/multiselect'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import { computed, onMounted, ref, watch } from 'vue'
import geocodingApi from '../../api/geocoding.js'
import { useInstrumentStore } from '../../store/attribute/instrument.js'
import { useStyleStore } from '../../store/attribute/style.js'
import { useTeacherProfileStore } from '../../store/user/teacherProfile.js'

const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  teacherProfile: {
    type: Object,
    default: null
  }
})

const emit = defineEmits(['update:visible', 'saved'])

const teacherProfileStore = useTeacherProfileStore()
const instrumentStore = useInstrumentStore()
const styleStore = useStyleStore()

const description = ref('')
const yearsOfExperience = ref(null)
const studentLevels = ref([])
const ageGroups = ref([])
const courseTitle = ref('')
const offersTrial = ref(false)
const trialPriceDisplay = ref(null)
const locations = ref([])
const selectedDurations = ref([])
const pricingByDuration = ref({})
const availabilitySlots = ref([])
const packages = ref([])
const socialLinks = ref([])
const selectedInstrumentIds = ref([])
const selectedStyleIds = ref([])
const error = ref('')
const isSaving = ref(false)

const instrumentOptions = computed(() => instrumentStore.instruments || [])
const styleOptions = computed(() => styleStore.styles || [])
const selectedDurationOptions = computed(() =>
  durationOptions.filter((d) => selectedDurations.value.includes(d.value))
)

const studentLevelOptions = [
  { value: 'beginner', label: 'Débutant' },
  { value: 'intermediate', label: 'Intermédiaire' },
  { value: 'advanced', label: 'Avancé' }
]

const ageGroupOptions = [
  { value: 'children', label: 'Enfants' },
  { value: 'teenagers', label: 'Adolescents' },
  { value: 'adults', label: 'Adultes' },
  { value: 'seniors', label: 'Seniors' }
]

const locationTypeOptions = [
  { value: 'teacher_place', label: 'Chez le professeur' },
  { value: 'student_place', label: 'Chez l\'élève' },
  { value: 'online', label: 'En ligne' }
]

const durationOptions = [
  { value: '30min', label: '30 min' },
  { value: '1h', label: '1 heure' },
  { value: '1h30', label: '1h30' },
  { value: '2h', label: '2 heures' }
]

const dayOfWeekOptions = [
  { value: 'monday', label: 'Lundi' },
  { value: 'tuesday', label: 'Mardi' },
  { value: 'wednesday', label: 'Mercredi' },
  { value: 'thursday', label: 'Jeudi' },
  { value: 'friday', label: 'Vendredi' },
  { value: 'saturday', label: 'Samedi' },
  { value: 'sunday', label: 'Dimanche' }
]

const socialPlatformOptions = [
  { value: 'website', label: 'Site web' },
  { value: 'youtube', label: 'YouTube' },
  { value: 'instagram', label: 'Instagram' },
  { value: 'facebook', label: 'Facebook' },
  { value: 'twitter', label: 'Twitter / X' },
  { value: 'tiktok', label: 'TikTok' },
  { value: 'spotify', label: 'Spotify' },
  { value: 'soundcloud', label: 'SoundCloud' },
  { value: 'bandcamp', label: 'Bandcamp' }
]

const isVisible = computed({
  get: () => props.visible,
  set: (value) => emit('update:visible', value)
})

watch(() => props.visible, (visible) => {
  if (visible) {
    initForm()
    loadAttributesIfNeeded()
  }
})

function initForm() {
  error.value = ''

  if (props.teacherProfile) {
    description.value = props.teacherProfile.description || ''
    yearsOfExperience.value = props.teacherProfile.years_of_experience || null
    studentLevels.value = props.teacherProfile.student_levels || []
    ageGroups.value = props.teacherProfile.age_groups || []
    courseTitle.value = props.teacherProfile.course_title || ''
    offersTrial.value = props.teacherProfile.offers_trial || false
    trialPriceDisplay.value = props.teacherProfile.trial_price != null
      ? props.teacherProfile.trial_price / 100
      : null

    // Initialize locations
    locations.value = (props.teacherProfile.locations || []).map((loc) => ({
      type: loc.type,
      address: loc.address || '',
      city: loc.city || '',
      country: loc.country || '',
      latitude: loc.latitude || null,
      longitude: loc.longitude || null,
      radius: loc.radius || null,
      locationSearch: loc.city ? (loc.country ? `${loc.city}, ${loc.country}` : loc.city) : '',
      suggestions: []
    }))

    // Initialize pricing by duration
    pricingByDuration.value = {}
    selectedDurations.value = []
    if (props.teacherProfile.pricing) {
      for (const p of props.teacherProfile.pricing) {
        pricingByDuration.value[p.duration] = p.price / 100
        selectedDurations.value.push(p.duration)
      }
    }

    // Initialize availability slots
    availabilitySlots.value = (props.teacherProfile.availability || []).map((a) => ({
      dayOfWeek: a.day_of_week,
      startTime: a.start_time,
      endTime: a.end_time
    }))

    // Initialize packages
    packages.value = (props.teacherProfile.packages || []).map((p) => ({
      title: p.title || '',
      description: p.description || '',
      sessionsCount: p.sessions_count || null,
      price: p.price != null ? p.price / 100 : null
    }))

    // Initialize social links
    socialLinks.value = (props.teacherProfile.social_links || []).map((link) => ({
      platform: link.platform,
      url: link.url
    }))

    selectedInstrumentIds.value = (props.teacherProfile.instruments || []).map(
      (i) => i.instrument_id
    )
    selectedStyleIds.value = (props.teacherProfile.styles || []).map((s) => s.id)
  } else {
    description.value = ''
    yearsOfExperience.value = null
    studentLevels.value = []
    ageGroups.value = []
    courseTitle.value = ''
    offersTrial.value = false
    trialPriceDisplay.value = null
    locations.value = []
    selectedDurations.value = []
    pricingByDuration.value = {}
    availabilitySlots.value = []
    packages.value = []
    socialLinks.value = []
    selectedInstrumentIds.value = []
    selectedStyleIds.value = []
  }
}

function addAvailabilitySlot() {
  availabilitySlots.value.push({
    dayOfWeek: 'monday',
    startTime: '09:00',
    endTime: '18:00'
  })
}

function removeAvailabilitySlot(index) {
  availabilitySlots.value.splice(index, 1)
}

function addLocation() {
  locations.value.push({
    type: 'teacher_place',
    address: '',
    city: '',
    country: '',
    latitude: null,
    longitude: null,
    radius: null,
    locationSearch: '',
    suggestions: []
  })
}

function addPackage() {
  packages.value.push({
    title: '',
    description: '',
    sessionsCount: null,
    price: null
  })
}

function removePackage(index) {
  packages.value.splice(index, 1)
}

function addSocialLink() {
  socialLinks.value.push({
    platform: null,
    url: ''
  })
}

function removeSocialLink(index) {
  socialLinks.value.splice(index, 1)
}

function removeLocation(index) {
  locations.value.splice(index, 1)
}

async function searchLocation(event, index) {
  const query = event.query
  if (query.length < 2) {
    locations.value[index].suggestions = []
    return
  }

  try {
    const results = await geocodingApi.searchCities(query, 5)
    locations.value[index].suggestions = results
  } catch (e) {
    locations.value[index].suggestions = []
  }
}

function selectLocation(event, index) {
  const selected = event.value
  locations.value[index].city = selected.name
  locations.value[index].country = selected.context.split(', ').pop() || ''
  locations.value[index].latitude = selected.latitude
  locations.value[index].longitude = selected.longitude
  locations.value[index].locationSearch = selected.fullName
}

async function loadAttributesIfNeeded() {
  if (!instrumentStore.instruments?.length) {
    await instrumentStore.loadInstruments()
  }
  if (!styleStore.styles?.length) {
    await styleStore.loadStyles()
  }
}

function handleClose() {
  error.value = ''
  emit('update:visible', false)
}

async function handleSave() {
  error.value = ''
  isSaving.value = true

  const trialPriceCents = trialPriceDisplay.value != null
    ? Math.round(trialPriceDisplay.value * 100)
    : null

  // Build pricing input array (only for selected durations with a price)
  const pricingInput = selectedDurations.value
    .filter((duration) => pricingByDuration.value[duration] != null && pricingByDuration.value[duration] > 0)
    .map((duration) => ({
      duration,
      price: Math.round(pricingByDuration.value[duration] * 100)
    }))

  // Build availability input array
  const availabilityInput = availabilitySlots.value
    .filter((slot) => slot.dayOfWeek && slot.startTime && slot.endTime)
    .map((slot) => ({
      day_of_week: slot.dayOfWeek,
      start_time: slot.startTime,
      end_time: slot.endTime
    }))

  // Build locations input array
  const locationsInput = locations.value
    .filter((loc) => loc.type)
    .map((loc) => ({
      type: loc.type,
      address: loc.type === 'teacher_place' ? (loc.address || null) : null,
      city: loc.city || null,
      country: loc.country || null,
      latitude: loc.latitude || null,
      longitude: loc.longitude || null,
      radius: loc.type === 'student_place' ? loc.radius : null
    }))

  // Build packages input array
  const packagesInput = packages.value
    .filter((pkg) => pkg.title && pkg.title.trim())
    .map((pkg) => ({
      title: pkg.title.trim(),
      description: pkg.description ? pkg.description.trim() : null,
      sessions_count: pkg.sessionsCount || null,
      price: pkg.price != null ? Math.round(pkg.price * 100) : 0
    }))

  // Build social links input array
  const socialLinksInput = socialLinks.value
    .filter((link) => link.platform && link.url && link.url.trim())
    .map((link) => ({
      platform: link.platform,
      url: link.url.trim()
    }))

  const data = {
    description: description.value || null,
    years_of_experience: yearsOfExperience.value,
    student_levels: studentLevels.value,
    age_groups: ageGroups.value,
    course_title: courseTitle.value || null,
    offers_trial: offersTrial.value,
    trial_price: offersTrial.value ? trialPriceCents : null,
    locations: locationsInput,
    pricing: pricingInput,
    availability: availabilityInput,
    packages: packagesInput,
    social_links: socialLinksInput,
    instrument_ids: selectedInstrumentIds.value,
    style_ids: selectedStyleIds.value
  }

  try {
    if (props.teacherProfile) {
      await teacherProfileStore.updateProfile(data)
    } else {
      await teacherProfileStore.createProfile(data)
    }
    emit('update:visible', false)
    emit('saved')
  } catch (e) {
    if (e.violations?.length) {
      error.value = e.violations.map((v) => v.message).join('. ')
    } else {
      error.value = e.message || 'Une erreur est survenue'
    }
  } finally {
    isSaving.value = false
  }
}

onMounted(() => {
  loadAttributesIfNeeded()
})
</script>
