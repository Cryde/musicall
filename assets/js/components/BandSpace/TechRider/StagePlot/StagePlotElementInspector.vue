<template>
  <section class="flex flex-col gap-2 min-w-0" :aria-label="t('band_space_tech_rider.inspector.label')">
    <h4 class="font-semibold text-sm">{{ t('band_space_tech_rider.inspector.title') }}</h4>

    <p v-if="!element" class="text-xs text-surface-600 dark:text-surface-300 py-2">
      {{ t('band_space_tech_rider.inspector.empty') }}
    </p>

    <template v-else>
      <p class="text-xs text-surface-600 dark:text-surface-300">
        {{ iconLabel }} &middot; {{ describePosition(element.x, element.y) }}
      </p>

      <div>
        <label :for="`${uid}-label`" class="block text-xs font-medium mb-1">{{
          t('band_space_tech_rider.inspector.label_field')
        }}</label>
        <InputText
          :id="`${uid}-label`"
          :model-value="element.label ?? ''"
          :disabled="readOnly"
          :maxlength="MAX_LABEL_LENGTH"
          class="w-full"
          size="small"
          @update:model-value="(value) => update({ label: value })"
        />
      </div>

      <div>
        <label :for="`${uid}-colour`" class="block text-xs font-medium mb-1">{{
          t('band_space_tech_rider.common.colour')
        }}</label>
        <Select
          :id="`${uid}-colour`"
          :model-value="element.colour ?? null"
          :options="colourOptions"
          option-label="label"
          option-value="value"
          :disabled="readOnly"
          class="w-full"
          size="small"
          @update:model-value="(value) => update({ colour: value })"
        >
          <!-- The swatch never travels alone: the name is what carries the meaning if the colour
               cannot be seen, and the element's label is what carries it on the stage itself. -->
          <template #value="{ value }">
            <span class="flex items-center gap-2">
              <span
                class="w-3 h-3 rounded-sm border border-surface-300 dark:border-surface-600 shrink-0"
                :style="{ backgroundColor: hexFor(value) ?? 'transparent' }"
                aria-hidden="true"
              />
              <span class="truncate">{{ labelFor(value) }}</span>
            </span>
          </template>
          <template #option="{ option }">
            <span class="flex items-center gap-2">
              <span
                class="w-3 h-3 rounded-sm border border-surface-300 dark:border-surface-600 shrink-0"
                :style="{ backgroundColor: option.hex ?? 'transparent' }"
                aria-hidden="true"
              />
              <span>{{ option.label }}</span>
            </span>
          </template>
        </Select>
      </div>

      <div>
        <label :for="`${uid}-scale`" class="block text-xs font-medium mb-1">
          {{ t('band_space_tech_rider.inspector.scale', { scale: (element.scale ?? 1).toFixed(2) }) }}
        </label>
        <Slider
          :id="`${uid}-scale`"
          :model-value="element.scale ?? 1"
          :min="MIN_SCALE"
          :max="MAX_SCALE"
          :step="SCALE_STEP"
          :disabled="readOnly"
          class="w-full"
          :aria-label="t('band_space_tech_rider.inspector.scale_aria', { icon: iconLabel })"
          @update:model-value="(value) => update({ scale: value })"
        />
      </div>

      <div>
        <label :for="`${uid}-rotation`" class="block text-xs font-medium mb-1">
          {{ t('band_space_tech_rider.inspector.rotation', { degrees: element.rotation ?? 0 }) }}
        </label>
        <!-- Whole degrees, so the slider can represent every angle the server accepts. A coarser
             step would quietly rewrite an angle placed freely on the canvas the moment anyone
             touched this control. -->
        <Slider
          :id="`${uid}-rotation`"
          :model-value="element.rotation ?? 0"
          :min="MIN_ROTATION"
          :max="MAX_ROTATION"
          :step="FINE_ROTATION_STEP"
          :disabled="readOnly"
          class="w-full"
          :aria-label="t('band_space_tech_rider.inspector.rotation_aria', { icon: iconLabel })"
          @update:model-value="(value) => update({ rotation: value })"
        />
        <!-- The quarter turns kept as shortcuts beside the slider. They are one click where the
             slider is an aim, and they are the keyboard path: arrows on a 360 stop slider would
             need ninety presses to turn a corner. -->
        <div class="flex gap-1 mt-1" role="group" :aria-label="t('band_space_tech_rider.inspector.quarter_turns')">
          <Button
            v-for="rotation in ROTATIONS"
            :key="rotation"
            :label="t('band_space_tech_rider.inspector.quarter_turn', { degrees: rotation })"
            size="small"
            :severity="(element.rotation ?? 0) === rotation ? 'primary' : 'secondary'"
            :outlined="(element.rotation ?? 0) !== rotation"
            text
            :disabled="readOnly"
            :aria-pressed="(element.rotation ?? 0) === rotation"
            @click="update({ rotation })"
          />
        </div>
      </div>

      <div v-if="!readOnly" class="flex gap-1 pt-1">
        <Button
          :label="t('band_space_tech_rider.common.duplicate')"
          icon="pi pi-copy"
          severity="secondary"
          outlined
          size="small"
          :aria-label="t('band_space_tech_rider.inspector.duplicate_aria', { icon: iconLabel })"
          @click="emit('duplicate', element.id)"
        />
        <Button
          :label="t('band_space_tech_rider.common.remove')"
          icon="pi pi-trash"
          severity="danger"
          outlined
          size="small"
          :aria-label="t('band_space_tech_rider.inspector.remove_aria', { icon: iconLabel })"
          @click="emit('delete', element.id)"
        />
      </div>
    </template>
  </section>
</template>

<script setup>
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Slider from 'primevue/slider'
import { computed, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  describePosition,
  FINE_ROTATION_STEP,
  MAX_LABEL_LENGTH,
  MAX_ROTATION,
  MAX_SCALE,
  MIN_ROTATION,
  MIN_SCALE,
  ROTATIONS,
  SCALE_STEP
} from '../../../../constants/stagePlot.js'
import { TECH_RIDER_COLOURS, techRiderColourLabel } from '../../../../constants/techRiderColours.js'

const props = defineProps({
  element: { type: Object, default: null },
  icons: { type: Array, default: () => [] },
  readOnly: { type: Boolean, default: false }
})

const emit = defineEmits(['update', 'duplicate', 'delete'])

const { t } = useI18n()

const uid = useId()

const colourOptions = computed(() => [
  { value: null, label: labelFor(null), hex: null },
  ...TECH_RIDER_COLOURS.map((colour) => ({
    value: colour.value,
    label: labelFor(colour.value),
    hex: colour.hex
  }))
])

const iconLabel = computed(() => {
  const slug = props.element?.icon
  return props.icons.find((icon) => icon.slug === slug)?.label ?? slug ?? ''
})

function hexFor(value) {
  return TECH_RIDER_COLOURS.find((colour) => colour.value === value)?.hex ?? null
}

/** « Aucune » for no colour, as for one the palette does not know. */
function labelFor(value) {
  return techRiderColourLabel(value)
}

function update(patch) {
  emit('update', { id: props.element.id, ...patch })
}
</script>
