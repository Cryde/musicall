export const DELETED_DISPLAY_NAME = 'Utilisateur supprimé'

export function displayName(user) {
  if (user.deletion_datetime) {
    return DELETED_DISPLAY_NAME
  }
  return user.username
}
