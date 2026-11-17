/**
 * The sound engineer search is merged but not yet announced: its menu entries show to ROLE_TESTER
 * accounts only. Announcing it is setting this to false.
 */
export const SOUND_ENGINEER_SEARCH_TESTER_ONLY = true

/**
 * The sound engineer kind's values (#1410), each with the full key of its label, so the i18n check finds them. The
 * order is the one the form offers them in.
 */
export const SPECIALITY_KEYS = {
  mixing: 'profile.sound_engineer.specialities.mixing',
  mastering: 'profile.sound_engineer.specialities.mastering',
  recording: 'profile.sound_engineer.specialities.recording',
  live_recording: 'profile.sound_engineer.specialities.live_recording',
  live_sound: 'profile.sound_engineer.specialities.live_sound',
  editing: 'profile.sound_engineer.specialities.editing'
}

export const FORMAT_KEYS = {
  wav: 'profile.sound_engineer.formats.wav',
  stems: 'profile.sound_engineer.formats.stems',
  ddp: 'profile.sound_engineer.formats.ddp',
  mp3: 'profile.sound_engineer.formats.mp3'
}

/** What a price buys, after the price: « 80 € le titre ». */
export const UNIT_KEYS = {
  track: 'profile.sound_engineer.units.track',
  hour: 'profile.sound_engineer.units.hour',
  day: 'profile.sound_engineer.units.day',
  flat: 'profile.sound_engineer.units.flat'
}

/** The same units as the form names them: « Par titre ». */
export const FORM_UNIT_KEYS = {
  track: 'profile.sound_engineer.form.units.track',
  hour: 'profile.sound_engineer.form.units.hour',
  day: 'profile.sound_engineer.form.units.day',
  flat: 'profile.sound_engineer.form.units.flat'
}

export const PLACE_KEYS = {
  at_my_place: 'profile.sound_engineer.places.at_my_place',
  at_client_place: 'profile.sound_engineer.places.at_client_place',
  remote: 'profile.sound_engineer.places.remote'
}

/** The form's two located places, by their key in placesForm(). */
export const FORM_PLACE_KEYS = {
  studio: 'profile.sound_engineer.form.place_studio',
  client: 'profile.sound_engineer.form.place_client'
}

export const SIDE_KEYS = {
  before: 'profile.sound_engineer_page.side.before',
  after: 'profile.sound_engineer_page.side.after'
}
