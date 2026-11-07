<template>
  <Dialog
    v-model:visible="isVisible"
    modal
    :header="t(block ? BLOCK_TITLES[block] : 'profile.musician.edit.title')"
    :style="{ width: block ? '32rem' : '40rem' }"
    :closable="!isSaving"
    :closeOnEscape="!isSaving"
  >
    <Message v-if="error" severity="error" :closable="false" class="mb-4">
      {{ error }}
    </Message>

    <div class="flex flex-col gap-5">
      <!-- Clearing it is not offered alone: the PATCH reads a null as « left out » (#1327). -->
      <MusicianAvailabilityField
        v-if="shows('availability')"
        v-model="form.availabilityStatus"
        :clearable="!block"
        :disabled="isSaving"
      />
      <MusicianInstrumentsField v-if="shows('instruments')" v-model="form.instruments" :disabled="isSaving" />
      <MusicianStylesField v-if="shows('styles')" v-model="form.styleIds" :disabled="isSaving" />
      <MusicianBandsField v-if="shows('bands')" v-model="form.bands" :disabled="isSaving" />
    </div>

    <template #footer>
      <div class="flex justify-end gap-2">
        <Button
          :label="t('profile.musician.edit.cancel')"
          severity="secondary"
          :disabled="isSaving"
          @click="handleClose"
        />
        <Button
          :label="t('profile.musician.edit.save')"
          icon="pi pi-check"
          :loading="isSaving"
          @click="handleSave"
        />
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import Message from 'primevue/message'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useInstrumentStore } from '../../../store/attribute/instrument.js'
import { useStyleStore } from '../../../store/attribute/style.js'
import { useMusicianProfileStore } from '../../../store/user/musicianProfile.js'
import {
  MUSICIAN_BLOCKS,
  musicianForm,
  musicianPayload,
  musicianViolationMessage
} from '../../../utils/musicianProfileForm.js'
import MusicianAvailabilityField from './MusicianFields/MusicianAvailabilityField.vue'
import MusicianBandsField from './MusicianFields/MusicianBandsField.vue'
import MusicianInstrumentsField from './MusicianFields/MusicianInstrumentsField.vue'
import MusicianStylesField from './MusicianFields/MusicianStylesField.vue'

/**
 * The musician profile's form: whole in the settings and at creation, or one `block` of it from its pencil on the
 * profile (#1317), sending only that block so the others keep their value.
 */
const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  musicianProfile: {
    type: Object,
    default: null
  },
  /** One of MUSICIAN_BLOCKS, or null for the whole form. */
  block: {
    type: String,
    default: null
  }
})

const emit = defineEmits(['update:visible', 'saved'])

const BLOCK_TITLES = {
  availability: 'profile.musician.edit.block_title.availability',
  instruments: 'profile.musician.edit.block_title.instruments',
  styles: 'profile.musician.edit.block_title.styles',
  bands: 'profile.musician.edit.block_title.bands'
}
const { t } = useI18n()

const musicianProfileStore = useMusicianProfileStore()
const instrumentStore = useInstrumentStore()
const styleStore = useStyleStore()

const form = ref(musicianForm(null))
const error = ref('')
const isSaving = ref(false)

const blocks = computed(() => (props.block ? [props.block] : MUSICIAN_BLOCKS))
const shows = (name) => blocks.value.includes(name)

const isVisible = computed({
  get: () => props.visible,
  set: (value) => emit('update:visible', value)
})

watch(
  () => props.visible,
  (visible) => {
    if (visible) {
      error.value = ''
      form.value = musicianForm(props.musicianProfile)
      loadAttributesIfNeeded()
    }
  }
)

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
  const data = musicianPayload(form.value, blocks.value)

  try {
    if (props.musicianProfile) {
      await musicianProfileStore.updateProfile(data)
    } else {
      await musicianProfileStore.createProfile(data)
    }
    emit('update:visible', false)
    emit('saved')
  } catch (e) {
    if (e.violations?.length) {
      error.value = e.violations
        .map((violation) => musicianViolationMessage(violation, t))
        .join('. ')
    } else {
      error.value = e.message || t('profile.musician.edit.error')
    }
  } finally {
    isSaving.value = false
  }
}

onMounted(() => {
  loadAttributesIfNeeded()
})
</script>
