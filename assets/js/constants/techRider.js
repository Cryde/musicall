import { i18n } from '../i18n/index.js'

/**
 * Mirrors `App\Procedure\BandSpace\TechRiderDuplicateProcedure`, whose constants these are.
 *
 * Duplicated because the duplicate dialog proposes a name in its field rather than letting the
 * server apply its default, so the client has to do the same arithmetic or offer a name the server
 * will refuse. Pinned against the PHP by
 * tests/Unit/Procedure/BandSpace/TechRiderDuplicateNameLimitsTest.php.
 */
export const MAX_NAME_LENGTH = 255

export const COPY_SUFFIX = ' (copie)'

/**
 * The section types a rider can hold, as the add-section picker offers them and the outline shows
 * them (#1091). Mirrors TechRiderItemType: a type missing here cannot be added from the editor.
 */
export const RIDER_SECTION_TYPES = Object.freeze([
  {
    value: 'text',
    icon: 'pi-align-left',
    get label() {
      return i18n.global.t('band_space_tech_rider.section_type.text')
    },
    get description() {
      return i18n.global.t('band_space_tech_rider.section_type.text_description')
    }
  },
  {
    value: 'stage_plot',
    icon: 'pi-map',
    get label() {
      return i18n.global.t('band_space_tech_rider.section_type.stage_plot')
    },
    get description() {
      return i18n.global.t('band_space_tech_rider.section_type.stage_plot_description')
    }
  },
  {
    value: 'patch_list',
    icon: 'pi-sliders-h',
    get label() {
      return i18n.global.t('band_space_tech_rider.section_type.patch_list')
    },
    get description() {
      return i18n.global.t('band_space_tech_rider.section_type.patch_list_description')
    }
  },
  {
    value: 'contacts',
    icon: 'pi-users',
    get label() {
      return i18n.global.t('band_space_tech_rider.section_type.contacts')
    },
    get description() {
      return i18n.global.t('band_space_tech_rider.section_type.contacts_description')
    }
  },
  {
    value: 'document',
    icon: 'pi-file',
    get label() {
      return i18n.global.t('band_space_tech_rider.section_type.document')
    },
    get description() {
      return i18n.global.t('band_space_tech_rider.section_type.document_description')
    }
  }
])

export function riderSectionTypeIcon(type) {
  return (
    RIDER_SECTION_TYPES.find((sectionType) => sectionType.value === type)?.icon ?? 'pi-align-left'
  )
}
