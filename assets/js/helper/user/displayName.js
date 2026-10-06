export const DELETED_DISPLAY_NAME = 'Utilisateur supprimé'

/** The name the site shows for a user: their profile name when the API sends one (#1118). */
export function displayName(user) {
  if (user.deletion_datetime) {
    return DELETED_DISPLAY_NAME
  }
  return user.display_name || user.username
}
