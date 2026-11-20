/**
 * Which GeneralUser files a song needs. The bank is split at build time into one SoundFont per
 * instrument (see vite.config.js), listed in a manifest of `{ bank, program, name, file, size }`; the
 * reader downloads only the song's instruments instead of the 31 MB bank.
 *
 * Only the main bank (0) and the drum kits (128) ship, so a melodic track always plays bank 0: the
 * reader sets the track's bank to 0 too, or a file saved with a variation bank would play nothing.
 */
export const MELODIC_BANK = 0
export const DRUM_BANK = 128

/** The manifest entry a track plays: its program in bank 0, or its kit, the standard kit failing that. */
export function presetForTrack(track, presets) {
  const find = (bank, program) =>
    presets.find((preset) => preset.bank === bank && preset.program === program)
  if (track.isPercussion) return find(DRUM_BANK, track.program) ?? find(DRUM_BANK, 0) ?? null
  return find(MELODIC_BANK, track.program) ?? null
}

/** The files to download for these tracks, each once, in manifest order. */
export function soundFontFilesFor(tracks, presets) {
  const wanted = new Set(tracks.map((track) => presetForTrack(track, presets)).filter(Boolean))
  return presets.filter((preset) => wanted.has(preset))
}
