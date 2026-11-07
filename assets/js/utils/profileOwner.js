/** The owner's own profile (#1317): what each block shows them, and where a new media can go. */

/** Marks a block the owner can edit; drawn inside the card, so a scrolling column does not clip it. */
export const OWNER_CARD_CLASS = 'outline-2 outline-dashed -outline-offset-2 outline-primary'

/** A profile holds at most this many media, musician and teacher each. */
export const MAX_PROFILE_MEDIA = 6

/**
 * What a list card shows: its items, a hint for an owner who can fill it in, or nothing. A visitor never sees an
 * empty card.
 */
export function ownerCardContent(items, editable) {
  if (items?.length) return 'items'
  return editable ? 'hint' : null
}

/** The profiles a new media can be added to: those the member has, while they have room. */
export function mediaAddTargets(profile) {
  return ['musician', 'teacher'].filter(
    (kind) => profile?.[kind] && (profile[kind].media ?? []).length < MAX_PROFILE_MEDIA
  )
}
