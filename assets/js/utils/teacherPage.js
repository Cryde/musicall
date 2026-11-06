/** The teacher page's summaries of the profile data (#1310), kept out of the component so they are tested. */

const LEVEL_ORDER = ['beginner', 'intermediate', 'advanced']
const DURATION_ORDER = ['30min', '1h', '1h30', '2h']

export const DAYS_OF_WEEK = [
  'monday',
  'tuesday',
  'wednesday',
  'thursday',
  'friday',
  'saturday',
  'sunday'
]

/** The cheapest single lesson, the price a visitor compares first. */
export function cheapestPricing(pricing) {
  if (!Array.isArray(pricing) || pricing.length === 0) return null
  return pricing.reduce((cheapest, item) => (item.price < cheapest.price ? item : cheapest))
}

/** The prices from the shortest lesson to the longest, whatever order they were entered in. */
export function pricingByDuration(pricing) {
  if (!Array.isArray(pricing)) return []
  const rank = (item) => {
    const index = DURATION_ORDER.indexOf(item.duration)
    return index === -1 ? DURATION_ORDER.length : index
  }
  return [...pricing].sort((a, b) => rank(a) - rank(b))
}

/** The accepted levels in teaching order, whatever order they were ticked in; unknown values are dropped. */
export function orderedLevels(levels) {
  return LEVEL_ORDER.filter((level) => levels?.includes(level))
}

/** The first and last accepted level in teaching order, for « Débutant à avancé »; one level gives [it, it]. */
export function levelRange(levels) {
  const ordered = orderedLevels(levels)
  if (ordered.length === 0) return null
  return [ordered[0], ordered[ordered.length - 1]]
}

/** Where the lessons happen, for one line: the teacher's own city first, then any place's city. */
export function placesSummary(locations) {
  const places = Array.isArray(locations) ? locations : []
  const own = places.find((place) => place.type === 'teacher_place' && place.city)
  const anyCity = places.find((place) => place.city)
  return {
    city: (own ?? anyCity)?.city ?? null,
    atTeacher: places.some((place) => place.type === 'teacher_place'),
    atStudent: places.some((place) => place.type === 'student_place'),
    online: places.some((place) => place.type === 'online')
  }
}

/** The week from Monday, each day with its slots in time order, for the 7-day grid. */
export function availabilityByDay(slots) {
  const list = Array.isArray(slots) ? slots : []
  return DAYS_OF_WEEK.map((day) => ({
    day,
    slots: list
      .filter((slot) => slot.day_of_week === day)
      .map((slot) => ({ start: slot.start_time, end: slot.end_time }))
      .sort((a, b) => a.start.localeCompare(b.start))
  }))
}

export const MAX_SONGS = 8
const POINTS_PER_LEVEL = 3

/**
 * The edit form's « Ce que tu vas apprendre » and « Morceaux travaillés » (#1312): every level has its block ready,
 * so ticking a level shows its fields, and unticking it keeps what was typed until the form closes.
 */
export function lessonContentForm(profile) {
  const stored = Object.fromEntries((profile?.level_goals ?? []).map((goal) => [goal.level, goal]))
  const goals = Object.fromEntries(
    LEVEL_ORDER.map((level) => {
      const points = [...(stored[level]?.points ?? [])]
      while (points.length < POINTS_PER_LEVEL) points.push('')
      return [
        level,
        { title: stored[level]?.title ?? '', points: points.slice(0, POINTS_PER_LEVEL) }
      ]
    })
  )
  const songs = (profile?.songs ?? []).map((song) => ({
    artist: song.artist ?? '',
    title: song.title ?? ''
  }))
  return { goals, songs }
}

/** What the form sends: the goals of the levels ticked, without empty ones, and the songs that have a title. */
export function lessonContentPayload(form, studentLevels) {
  const levelGoals = orderedLevels(studentLevels)
    .map((level) => ({
      level,
      title: form.goals[level]?.title?.trim() ?? '',
      points: (form.goals[level]?.points ?? []).map((point) => point.trim()).filter(Boolean)
    }))
    .filter((goal) => goal.title || goal.points.length)
  const songs = form.songs
    .map((song) => ({ artist: song.artist?.trim() ?? '', title: song.title?.trim() ?? '' }))
    .filter((song) => song.title)
    .slice(0, MAX_SONGS)
  return { level_goals: levelGoals, songs }
}

const COMPLETENESS_PARTS = [
  ['course_title', (profile) => Boolean(profile.course_title)],
  ['description', (profile) => Boolean(profile.description)],
  ['instruments', (profile) => profile.instruments?.length > 0],
  ['student_levels', (profile) => profile.student_levels?.length > 0],
  ['age_groups', (profile) => profile.age_groups?.length > 0],
  ['level_goals', (profile) => profile.level_goals?.length > 0],
  ['songs', (profile) => profile.songs?.length > 0],
  ['locations', (profile) => profile.locations?.length > 0],
  ['availability', (profile) => profile.availability?.length > 0],
  ['pricing', (profile) => profile.pricing?.length > 0]
]

/** How much of the lessons page a teacher has filled, and what is missing, in the order the page reads. */
export function teacherProfileCompleteness(profile) {
  const missing = COMPLETENESS_PARTS.filter(([, isFilled]) => !isFilled(profile ?? {})).map(
    ([part]) => part
  )
  const total = COMPLETENESS_PARTS.length
  return {
    done: total - missing.length,
    total,
    percent: Math.round(((total - missing.length) / total) * 100),
    missing
  }
}
