// TeacherProfile label maps
// These maps are used to display labels in the UI from backend enum values

export const STUDENT_LEVEL_LABELS = {
  beginner: 'Débutant',
  intermediate: 'Intermédiaire',
  advanced: 'Avancé',
}

export const AGE_GROUP_LABELS = {
  children: 'Enfants',
  teenagers: 'Adolescents',
  adults: 'Adultes',
  seniors: 'Seniors',
}

export const LOCATION_TYPE_LABELS = {
  teacher_place: 'Chez le professeur',
  student_place: "Chez l'élève",
  online: 'En ligne',
}

export const SESSION_DURATION_LABELS = {
  '30min': '30 minutes',
  '45min': '45 minutes',
  '1h': '1 heure',
  '1h30': '1h30',
  '2h': '2 heures',
}

export const DAY_OF_WEEK_LABELS = {
  monday: 'Lundi',
  tuesday: 'Mardi',
  wednesday: 'Mercredi',
  thursday: 'Jeudi',
  friday: 'Vendredi',
  saturday: 'Samedi',
  sunday: 'Dimanche',
}

export const MEDIA_PLATFORM_LABELS = {
  youtube: 'YouTube',
  soundcloud: 'SoundCloud',
  spotify: 'Spotify',
  deezer: 'Deezer',
}

export const SOCIAL_PLATFORM_LABELS = {
  website: 'Site web',
  facebook: 'Facebook',
  instagram: 'Instagram',
  twitter: 'Twitter / X',
  youtube: 'YouTube',
  soundcloud: 'SoundCloud',
  spotify: 'Spotify',
  bandcamp: 'Bandcamp',
  tiktok: 'TikTok',
}

// Helper functions to get labels
export function getStudentLevelLabel(level) {
  return STUDENT_LEVEL_LABELS[level] || level
}

export function getAgeGroupLabel(ageGroup) {
  return AGE_GROUP_LABELS[ageGroup] || ageGroup
}

export function getLocationTypeLabel(type) {
  return LOCATION_TYPE_LABELS[type] || type
}

export function getSessionDurationLabel(duration) {
  return SESSION_DURATION_LABELS[duration] || duration
}

export function getDayOfWeekLabel(day) {
  return DAY_OF_WEEK_LABELS[day] || day
}

export function getMediaPlatformLabel(platform) {
  return MEDIA_PLATFORM_LABELS[platform] || platform
}

export function getSocialPlatformLabel(platform) {
  return SOCIAL_PLATFORM_LABELS[platform] || platform
}
