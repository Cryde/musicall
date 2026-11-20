/**
 * What kind of instrument a Guitar Pro track is, from its General MIDI program (0 to 127): the
 * reader shows « Guitare · 6 cordes » or « Batterie » under the track's name.
 */
export const TRACK_FAMILIES = Object.freeze({
  GUITAR: 'guitar',
  BASS: 'bass',
  DRUMS: 'drums',
  OTHER: 'other'
})

const GUITARS = [24, 31]
const BASSES = [32, 39]

export function trackFamily(program, isPercussion) {
  if (isPercussion) return TRACK_FAMILIES.DRUMS
  if (program >= GUITARS[0] && program <= GUITARS[1]) return TRACK_FAMILIES.GUITAR
  if (program >= BASSES[0] && program <= BASSES[1]) return TRACK_FAMILIES.BASS
  return TRACK_FAMILIES.OTHER
}

// One colour per track, in file order, the same in light and dark.
const TRACK_COLORS = Object.freeze([
  '#e11d48',
  '#2563eb',
  '#ca8a04',
  '#0d9488',
  '#7c3aed',
  '#ea580c'
])

export function trackColor(index) {
  return TRACK_COLORS[index % TRACK_COLORS.length]
}
