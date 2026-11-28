/** How many years back a start year may go, as the server checks it (YearsOfExperience::MAX, #1453). */
const MAX_YEARS = 70

/** The years « Tu exerces depuis » accepts today: never a future year, at most 70 years back. */
export function experienceStartYearRange(today = new Date()) {
  const max = today.getFullYear()

  return { min: max - MAX_YEARS, max }
}
