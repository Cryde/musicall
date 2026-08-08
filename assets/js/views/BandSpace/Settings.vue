<template>
  <div>
    <div class="grid grid-cols-12 gap-4">
      <div class="col-span-12 lg:col-span-10 xl:col-span-10 flex-auto">
        <div class="flex flex-col lg:flex-row gap-6">
          <!-- Sidebar menu (horizontal scrollable tabs on mobile, vertical on lg+) -->
          <div class="lg:w-56 shrink-0">
            <div class="bg-surface-0 dark:bg-surface-900 rounded-2xl p-2 lg:p-4">
              <nav
                class="flex flex-row lg:flex-col gap-1 overflow-x-auto lg:overflow-visible"
              >
                <button
                  v-for="section in visibleSections"
                  :key="section.key"
                  @click="activeSection = section.key"
                  :class="[
                    'text-left px-3 lg:px-4 py-2.5 rounded-lg transition-colors duration-150 text-sm font-medium',
                    'shrink-0 whitespace-nowrap lg:w-full',
                    activeSection === section.key
                      ? 'bg-primary text-primary-contrast'
                      : 'text-surface-600 dark:text-surface-300 hover:bg-surface-100 dark:hover:bg-surface-800'
                  ]"
                >
                  {{ section.label }}
                </button>
              </nav>
            </div>
          </div>

          <!-- Content area -->
          <div class="flex-1 min-w-0">
            <MembersSection v-if="activeSection === 'members'" />
            <ActivitySection v-else-if="activeSection === 'activity'" />
            <ActiveSharesSection v-else-if="activeSection === 'shares'" />
            <QuotaIndicator v-else-if="activeSection === 'storage'" />
            <DangerZoneSection v-else-if="activeSection === 'danger'" />
            <ComingSoonSection v-else :title="activeSectionLabel" />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import QuotaIndicator from '../../components/BandSpace/Files/QuotaIndicator.vue'
import ActiveSharesSection from '../../components/BandSpace/Settings/ActiveSharesSection.vue'
import ActivitySection from '../../components/BandSpace/Settings/ActivitySection.vue'
import ComingSoonSection from '../../components/BandSpace/Settings/ComingSoonSection.vue'
import DangerZoneSection from '../../components/BandSpace/Settings/DangerZoneSection.vue'
import MembersSection from '../../components/BandSpace/Settings/MembersSection.vue'
import { useBandSpaceNavigation } from '../../composables/useBandSpaceNavigation.js'
import {
  BAND_SPACE_SETTINGS_SECTIONS,
  resolveSettingsSection,
  visibleSettingsSections
} from '../../constants/bandSpace.js'

const { isAdmin } = useBandSpaceNavigation()
const route = useRoute()

const visibleSections = computed(() => visibleSettingsSections(isAdmin.value))

const activeSection = ref(resolveSettingsSection(route.query.section, isAdmin.value))

const activeSectionLabel = computed(
  () => BAND_SPACE_SETTINGS_SECTIONS.find((s) => s.key === activeSection.value)?.label ?? ''
)

// The deletion banner links here with ?section=danger, and it is shown on the settings page too. Vue
// Router reuses this instance on a query-only change, so without re-reading the query the URL would
// update while the visible section stayed put.
watch(
  () => route.query.section,
  (section) => {
    const match = visibleSections.value.find((s) => s.key === section)
    if (match) {
      activeSection.value = match.key
    }
  }
)

// The role only arrives with the space list, so the visible set can shrink after the first render.
// Re-resolving keeps the current section when it is still allowed and falls back otherwise.
watch(visibleSections, () => {
  activeSection.value = resolveSettingsSection(activeSection.value, isAdmin.value)
})
</script>
