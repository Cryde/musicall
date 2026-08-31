<template>
  <Drawer
    v-model:visible="isVisible"
    position="right"
    header="Indisponibilités"
    class="w-full md:w-[32rem]"
  >
    <div class="flex flex-col gap-4">
      <p class="text-sm text-surface-500 dark:text-surface-400">
        Qui est absent, et quand. Chacun gère ses propres dates, un administrateur peut gérer celles
        de tout le groupe.
      </p>

      <Message
        v-if="formError"
        severity="error"
        :closable="true"
        class="text-sm"
        @close="formError = null"
      >
        {{ formError }}
      </Message>

      <div class="flex flex-wrap items-end gap-2">
        <div class="flex flex-col gap-1">
          <label for="absence-year" class="text-xs font-medium">Année</label>
          <Select
            id="absence-year"
            v-model="selectedYear"
            :options="yearOptions"
            optionLabel="label"
            optionValue="value"
            class="w-28"
          />
        </div>
        <div class="flex flex-col gap-1 flex-1 min-w-40">
          <label for="absence-member-filter" class="text-xs font-medium">Membre</label>
          <Select
            id="absence-member-filter"
            v-model="selectedMemberId"
            :options="memberFilterOptions"
            optionLabel="label"
            optionValue="value"
            class="w-full"
          />
        </div>
        <Button
          v-if="!isFormOpen"
          icon="pi pi-plus"
          label="Ajouter"
          size="small"
          @click="openCreateForm"
        />
      </div>

      <form
        v-if="isFormOpen"
        class="flex flex-col gap-3 rounded-xl border border-surface-200 dark:border-surface-700 p-3"
        @submit.prevent="handleSubmit"
      >
        <div v-if="canPickMember" class="flex flex-col gap-1">
          <label for="absence-member" class="text-sm font-medium">Membre</label>
          <Select
            id="absence-member"
            v-model="form.memberId"
            :options="memberOptions"
            optionLabel="label"
            optionValue="value"
            :disabled="isEditing"
            class="w-full"
          />
          <small v-if="isEditing" class="text-surface-500 dark:text-surface-400">
            Pour changer de membre, supprimez cette indisponibilité et créez-en une autre.
          </small>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row">
          <div class="flex flex-col gap-1 flex-1">
            <label for="absence-start" class="text-sm font-medium">
              Du <span class="text-red-500">*</span>
            </label>
            <DatePicker
              id="absence-start"
              v-model="form.startDate"
              dateFormat="dd/mm/yy"
              showIcon
              class="w-full"
              :class="{ 'p-invalid': fieldErrors.startDate }"
            />
            <small v-if="fieldErrors.startDate" class="text-red-500">
              {{ fieldErrors.startDate }}
            </small>
          </div>
          <div class="flex flex-col gap-1 flex-1">
            <label for="absence-end" class="text-sm font-medium">
              Au <span class="text-red-500">*</span>
            </label>
            <DatePicker
              id="absence-end"
              v-model="form.endDate"
              dateFormat="dd/mm/yy"
              showIcon
              :minDate="form.startDate ?? undefined"
              class="w-full"
              :class="{ 'p-invalid': fieldErrors.endDate }"
            />
            <small v-if="fieldErrors.endDate" class="text-red-500">{{ fieldErrors.endDate }}</small>
          </div>
        </div>

        <div class="flex flex-col gap-1">
          <label for="absence-reason" class="text-sm font-medium">Motif (optionnel)</label>
          <InputText
            id="absence-reason"
            v-model="form.reason"
            maxlength="120"
            placeholder="Ex : vacances, déplacement professionnel"
            :class="{ 'p-invalid': fieldErrors.reason }"
          />
          <small v-if="fieldErrors.reason" class="text-red-500">{{ fieldErrors.reason }}</small>
        </div>

        <div class="flex justify-end gap-2">
          <Button
            label="Annuler"
            severity="secondary"
            text
            :disabled="absenceStore.isSaving"
            @click="closeForm"
          />
          <Button
            type="submit"
            :label="isEditing ? 'Enregistrer' : 'Ajouter'"
            :loading="absenceStore.isSaving"
          />
        </div>
      </form>

      <div v-if="absenceStore.isLoading" class="flex justify-center py-8">
        <ProgressSpinner style="width: 2.5rem; height: 2.5rem" />
      </div>

      <div v-else-if="absenceStore.loadError" class="text-center text-red-500 py-8">
        {{ absenceStore.loadError }}
      </div>

      <div
        v-else-if="groupedAbsences.length === 0"
        class="text-center text-surface-500 dark:text-surface-400 italic py-8"
      >
        Aucune indisponibilité enregistrée sur cette période
      </div>

      <div v-else class="flex flex-col gap-4">
        <div v-for="group in groupedAbsences" :key="group.key">
          <h3 class="text-xs font-semibold uppercase tracking-wide text-surface-500 dark:text-surface-400 mb-1">
            {{ group.label }}
          </h3>
          <ul class="rounded-xl border border-surface-200 dark:border-surface-700 overflow-hidden">
            <li
              v-for="absence in group.absences"
              :key="absence.id"
              class="flex items-center gap-3 p-3 border-b border-surface-200 dark:border-surface-700 last:border-b-0"
            >
              <Avatar
                :username="absence.display_name"
                :picture-url="absence.profile_picture_url"
                size="sm"
              />
              <div class="flex-1 min-w-0">
                <div class="font-medium truncate">{{ absence.display_name }}</div>
                <div class="text-sm text-surface-600 dark:text-surface-300 tabular-nums">
                  {{ formatAbsenceRange(absence.start_date, absence.end_date) }}
                </div>
                <div
                  v-if="absence.reason"
                  class="text-xs text-surface-500 dark:text-surface-400 truncate"
                >
                  {{ absence.reason }}
                </div>
              </div>
              <div v-if="absence.can_manage" class="flex items-center gap-1">
                <Button
                  icon="pi pi-pencil"
                  severity="secondary"
                  text
                  rounded
                  size="small"
                  aria-label="Modifier l'indisponibilité"
                  v-tooltip.bottom="'Modifier'"
                  @click="openEditForm(absence)"
                />
                <Button
                  icon="pi pi-trash"
                  severity="danger"
                  text
                  rounded
                  size="small"
                  aria-label="Supprimer l'indisponibilité"
                  v-tooltip.bottom="'Supprimer'"
                  @click="confirmDelete(absence)"
                />
              </div>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </Drawer>
</template>

<script setup>
import { format, parseISO } from 'date-fns'
import Button from 'primevue/button'
import DatePicker from 'primevue/datepicker'
import Drawer from 'primevue/drawer'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import ProgressSpinner from 'primevue/progressspinner'
import Select from 'primevue/select'
import { useConfirm } from 'primevue/useconfirm'
import { computed, reactive, ref, watch } from 'vue'
import { useBandSpaceNavigation } from '../../../composables/useBandSpaceNavigation.js'
import { useBandAbsenceStore } from '../../../store/bandSpace/bandSpaceAbsence.js'
import { useBandSpaceSettingsStore } from '../../../store/bandSpace/bandSpaceSettings.js'
import { useUserSecurityStore } from '../../../store/user/security.js'
import { formatAbsenceRange, groupAbsencesByMonth } from '../../../utils/absenceRange.js'
import Avatar from '../../User/Avatar.vue'

const props = defineProps({
  bandSpaceId: { type: String, required: true },
  /** Preselects a member in the filter, so clicking an absence on the calendar lands on that person. */
  initialMemberId: { type: String, default: null }
})

const emit = defineEmits(['changed'])
const isVisible = defineModel('visible', { type: Boolean, default: false })

const ALL_MEMBERS = '__all__'

const absenceStore = useBandAbsenceStore()
const confirm = useConfirm()
const { isAdmin } = useBandSpaceNavigation()
const userSecurityStore = useUserSecurityStore()
const settingsStore = useBandSpaceSettingsStore()

const currentYear = new Date().getFullYear()
const selectedYear = ref(currentYear)
const selectedMemberId = ref(ALL_MEMBERS)
const isFormOpen = ref(false)
const editingId = ref(null)
const formError = ref(null)
const fieldErrors = reactive({ startDate: null, endDate: null, reason: null })
const form = reactive({ memberId: null, startDate: null, endDate: null, reason: '' })

// Far enough back to keep last season readable, far enough forward for a tour being booked. Fixed at
// setup: nothing here is reactive, so a computed would only promise a reactivity it does not have.
const yearOptions = [currentYear - 1, currentYear, currentYear + 1, currentYear + 2].map(
  (year) => ({
    label: String(year),
    value: year
  })
)

const members = computed(() => settingsStore.members)

const memberOptions = computed(() =>
  members.value.map((member) => ({ label: member.display_name, value: member.id }))
)

const memberFilterOptions = computed(() => [
  { label: 'Tous', value: ALL_MEMBERS },
  ...memberOptions.value
])

/**
 * The reader's own membership in this band. Matched on the user id, which is the key the rest of the
 * app compares identity on, rather than on `display_name`, which may be a stage name. Only used to
 * preselect an admin's own name in the form, since a plain member never sends a member at all.
 */
const ownMemberId = computed(
  () =>
    members.value.find((member) => member.user_id === userSecurityStore.userProfile?.id)?.id ?? null
)

/** Only an admin may record for somebody else, so only an admin is offered the choice. */
const canPickMember = computed(() => isAdmin.value)

const isEditing = computed(() => editingId.value !== null)

const visibleAbsences = computed(() =>
  selectedMemberId.value === ALL_MEMBERS
    ? absenceStore.absences
    : absenceStore.absences.filter((absence) => absence.member_id === selectedMemberId.value)
)

const groupedAbsences = computed(() => groupAbsencesByMonth(visibleAbsences.value))

watch(isVisible, async (opened) => {
  if (!opened) {
    closeForm()
    return
  }

  selectedMemberId.value = props.initialMemberId ?? ALL_MEMBERS
  // Independent requests: the list rows carry their own name and avatar, and the roster only feeds
  // the pickers. Awaiting the roster first would add a round trip to the drawer's time to content.
  loadMembers()
  fetchYear()
})

watch(selectedYear, () => {
  if (isVisible.value) fetchYear()
})

async function loadMembers() {
  try {
    await settingsStore.loadMembers(props.bandSpaceId)
  } catch {
    // The roster only feeds the two pickers; the list itself carries every name it renders.
  }
}

function fetchYear() {
  absenceStore.fetchAbsences(props.bandSpaceId, {
    from: `${selectedYear.value}-01-01`,
    to: `${selectedYear.value}-12-31`
  })
}

function resetErrors() {
  formError.value = null
  fieldErrors.startDate = null
  fieldErrors.endDate = null
  fieldErrors.reason = null
}

function openCreateForm() {
  resetErrors()
  editingId.value = null
  form.memberId = ownMemberId.value ?? memberOptions.value[0]?.value ?? null
  form.startDate = null
  form.endDate = null
  form.reason = ''
  isFormOpen.value = true
}

function openEditForm(absence) {
  resetErrors()
  editingId.value = absence.id
  form.memberId = absence.member_id
  form.startDate = parseISO(absence.start_date)
  form.endDate = parseISO(absence.end_date)
  form.reason = absence.reason ?? ''
  isFormOpen.value = true
}

function closeForm() {
  isFormOpen.value = false
  editingId.value = null
  resetErrors()
}

async function handleSubmit() {
  resetErrors()

  if (!form.startDate) {
    fieldErrors.startDate = 'Veuillez spécifier une date de début'
    return
  }
  if (!form.endDate) {
    fieldErrors.endDate = 'Veuillez spécifier une date de fin'
    return
  }
  if (form.endDate < form.startDate) {
    fieldErrors.endDate = 'La date de fin doit être postérieure ou égale à la date de début'
    return
  }

  const reason = form.reason.trim()
  const payload = {
    // Local getters on purpose: the day the member picked in the calendar widget is the day that
    // gets stored, with no offset in the payload to move it.
    startDate: format(form.startDate, 'yyyy-MM-dd'),
    endDate: format(form.endDate, 'yyyy-MM-dd'),
    reason: reason === '' ? null : reason
  }

  try {
    if (isEditing.value) {
      await absenceStore.updateAbsence(props.bandSpaceId, editingId.value, payload)
    } else {
      await absenceStore.createAbsence(props.bandSpaceId, {
        ...payload,
        // Only sent by an admin recording for somebody else; the API defaults it to the caller.
        ...(canPickMember.value && form.memberId ? { memberId: form.memberId } : {})
      })
    }
    closeForm()
    emit('changed')
  } catch (error) {
    applyViolations(error)
    formError.value = error?.message ?? "Impossible d'enregistrer l'indisponibilité"
  }
}

function applyViolations(error) {
  const violations = error?.violationsByField
  if (!violations) return
  if (violations.start_date) fieldErrors.startDate = violations.start_date[0].message
  if (violations.end_date) fieldErrors.endDate = violations.end_date[0].message
  if (violations.reason) fieldErrors.reason = violations.reason[0].message
}

function confirmDelete(absence) {
  confirm.require({
    message: `Supprimer l'indisponibilité de ${absence.display_name} (${formatAbsenceRange(absence.start_date, absence.end_date)}) ?`,
    header: 'Confirmer la suppression',
    icon: 'pi pi-exclamation-triangle',
    rejectLabel: 'Annuler',
    acceptLabel: 'Supprimer',
    acceptClass: 'p-button-danger',
    accept: async () => {
      resetErrors()
      try {
        await absenceStore.deleteAbsence(props.bandSpaceId, absence.id)
        if (editingId.value === absence.id) closeForm()
        emit('changed')
      } catch (error) {
        formError.value = error?.message ?? "Impossible de supprimer l'indisponibilité"
      }
    }
  })
}
</script>
