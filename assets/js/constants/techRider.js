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
    label: 'Texte libre',
    icon: 'pi-align-left',
    description: 'Backline, catering, loges, horaires'
  },
  {
    value: 'stage_plot',
    label: 'Plan de scène',
    icon: 'pi-map',
    description: 'La disposition sur scène'
  },
  {
    value: 'patch_list',
    label: 'Patch list',
    icon: 'pi-sliders-h',
    description: 'Entrées et retours'
  },
  {
    value: 'contacts',
    label: 'Membres et contacts',
    icon: 'pi-users',
    description: 'Repris de la liste des membres'
  },
  {
    value: 'document',
    label: 'Document',
    icon: 'pi-file',
    description: 'Une image ou un PDF de vos fichiers'
  }
])

export function riderSectionTypeIcon(type) {
  return (
    RIDER_SECTION_TYPES.find((sectionType) => sectionType.value === type)?.icon ?? 'pi-align-left'
  )
}
