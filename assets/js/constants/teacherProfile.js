// TeacherProfile label maps
// These maps hold the catalogue key of each backend enum value; the helpers translate at call time

import { i18n } from '../i18n/index.js'

export const STUDENT_LEVEL_LABEL_KEYS = {
  beginner: 'profile.teacher.student_level.beginner',
  intermediate: 'profile.teacher.student_level.intermediate',
  advanced: 'profile.teacher.student_level.advanced'
}

export const AGE_GROUP_LABEL_KEYS = {
  children: 'profile.teacher.age_group.children',
  teenagers: 'profile.teacher.age_group.teenagers',
  adults: 'profile.teacher.age_group.adults',
  seniors: 'profile.teacher.age_group.seniors'
}

export const LOCATION_TYPE_LABEL_KEYS = {
  teacher_place: 'profile.teacher.location_type.teacher_place',
  student_place: 'profile.teacher.location_type.student_place',
  online: 'profile.teacher.location_type.online'
}

export const SESSION_DURATION_LABEL_KEYS = {
  '30min': 'profile.teacher.session_duration.min_30',
  '45min': 'profile.teacher.session_duration.min_45',
  '1h': 'profile.teacher.session_duration.h_1',
  '1h30': 'profile.teacher.session_duration.h_1_30',
  '2h': 'profile.teacher.session_duration.h_2'
}

export const DAY_OF_WEEK_LABEL_KEYS = {
  monday: 'profile.teacher.day_of_week.monday',
  tuesday: 'profile.teacher.day_of_week.tuesday',
  wednesday: 'profile.teacher.day_of_week.wednesday',
  thursday: 'profile.teacher.day_of_week.thursday',
  friday: 'profile.teacher.day_of_week.friday',
  saturday: 'profile.teacher.day_of_week.saturday',
  sunday: 'profile.teacher.day_of_week.sunday'
}

export const MEDIA_PLATFORM_LABEL_KEYS = {
  youtube: 'profile.platform.youtube',
  soundcloud: 'profile.platform.soundcloud',
  spotify: 'profile.platform.spotify',
  deezer: 'profile.platform.deezer'
}

export const SOCIAL_PLATFORM_LABEL_KEYS = {
  website: 'profile.platform.website',
  facebook: 'profile.platform.facebook',
  instagram: 'profile.platform.instagram',
  twitter: 'profile.platform.twitter',
  youtube: 'profile.platform.youtube',
  soundcloud: 'profile.platform.soundcloud',
  spotify: 'profile.platform.spotify',
  bandcamp: 'profile.platform.bandcamp',
  tiktok: 'profile.platform.tiktok'
}

function labelFor(keys, value) {
  const key = keys[value]
  return key ? i18n.global.t(key) : value
}

// Helper functions to get labels
export function getStudentLevelLabel(level) {
  return labelFor(STUDENT_LEVEL_LABEL_KEYS, level)
}

export function getAgeGroupLabel(ageGroup) {
  return labelFor(AGE_GROUP_LABEL_KEYS, ageGroup)
}

export function getLocationTypeLabel(type) {
  return labelFor(LOCATION_TYPE_LABEL_KEYS, type)
}

export function getSessionDurationLabel(duration) {
  return labelFor(SESSION_DURATION_LABEL_KEYS, duration)
}

export function getDayOfWeekLabel(day) {
  return labelFor(DAY_OF_WEEK_LABEL_KEYS, day)
}

export function getMediaPlatformLabel(platform) {
  return labelFor(MEDIA_PLATFORM_LABEL_KEYS, platform)
}

export function getSocialPlatformLabel(platform) {
  return labelFor(SOCIAL_PLATFORM_LABEL_KEYS, platform)
}
