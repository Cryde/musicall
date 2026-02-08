export function displayName(user) {
  if (user.deletion_datetime) {
    return 'Utilisateur supprimé'
  }
  return user.username
}
