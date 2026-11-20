/** Playback speed of the tab reader, as a ratio of the written tempo. */
export const SPEED = Object.freeze({ MIN: 0.5, MAX: 1, STEP: 0.05, TRAINER_START: 0.7 })

/** A speed on the 5 % grid, between 50 and 100 %. */
export function clampSpeed(value) {
  const stepped = Math.round(value / SPEED.STEP) * SPEED.STEP
  return Number(Math.min(SPEED.MAX, Math.max(SPEED.MIN, stepped)).toFixed(2))
}

/**
 * The speed for the next pass of a loop with « Accélérer à chaque boucle » on: 5 % faster, up to
 * 100 %. Null when nothing changes: trainer off, nothing looping, or already at full speed.
 */
export function trainerNextSpeed(speed, { trainer, looping }) {
  if (!trainer || !looping || speed >= SPEED.MAX) return null
  return clampSpeed(speed + SPEED.STEP)
}

/** The list with the index added, or taken out when it was in. */
export function toggleIndex(list, index) {
  return list.includes(index) ? list.filter((item) => item !== index) : [...list, index]
}
