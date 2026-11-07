const hasMedia = (profile) =>
  Boolean(profile.musician?.media?.length || profile.teacher?.media?.length)

/** What a complete profile has, in the order the owner's checklist reads (#1317). `applies` leaves out what needs a profile they lack. */
const CHECKLIST_ITEMS = [
  {
    key: 'photos',
    applies: () => true,
    isDone: (profile) => Boolean(profile.profile_picture_url && profile.cover_picture_url)
  },
  { key: 'bio', applies: () => true, isDone: (profile) => Boolean(profile.bio?.trim()) },
  {
    key: 'instruments',
    applies: (profile) => Boolean(profile.musician),
    isDone: (profile) => Boolean(profile.musician.instruments?.length)
  },
  {
    key: 'media',
    applies: (profile) => Boolean(profile.musician || profile.teacher),
    isDone: hasMedia
  },
  { key: 'post', applies: () => true, isDone: (profile) => Boolean(profile.has_posts) },
  {
    key: 'availability',
    applies: (profile) => Boolean(profile.teacher),
    isDone: (profile) => Boolean(profile.teacher.availability?.length)
  }
]

/** « Ton profil est complet à N % »: each item that applies to the member, done or not, and the share done. */
export function profileChecklist(profile) {
  const items = CHECKLIST_ITEMS.filter((item) => item.applies(profile)).map((item) => ({
    key: item.key,
    done: item.isDone(profile)
  }))
  const done = items.filter((item) => item.done).length

  return { items, percent: Math.round((done / items.length) * 100) }
}
