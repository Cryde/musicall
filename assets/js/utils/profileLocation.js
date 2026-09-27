/**
 * The profile « Localisation » (#1079): a city picked in the geocoder carries its coordinates, text
 * typed before the city picker existed does not, and is kept as it was written.
 */

/** The member's city, when their location has coordinates. */
export function profileCity(profile) {
  if (profile?.latitude == null || profile?.longitude == null || !profile.location) return null
  return { name: profile.location, latitude: profile.latitude, longitude: profile.longitude }
}

/** What the city picker starts from: the city, or the text as the member typed it. */
export function locationFieldValue(profile) {
  return profileCity(profile) ?? profile?.location ?? null
}

/** The fields to save for what the picker holds: a picked city, or text with no coordinates. */
export function profileLocationPayload(value) {
  if (value && typeof value === 'object') {
    return { location: value.name, latitude: value.latitude, longitude: value.longitude }
  }
  const text = typeof value === 'string' ? value.trim() : ''
  return { location: text || null, latitude: null, longitude: null }
}

/**
 * The location fields to save, or null when the picker still holds what it started from. A form
 * that did not touch the location leaves it out, so it never drops coordinates it was not shown.
 */
export function editedLocationPayload(value, initialValue) {
  const edited = profileLocationPayload(value)
  const initial = profileLocationPayload(initialValue)
  const isUnchanged =
    edited.location === initial.location &&
    edited.latitude === initial.latitude &&
    edited.longitude === initial.longitude
  return isUnchanged ? null : edited
}
