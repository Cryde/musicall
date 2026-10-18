import { deletedDisplayName } from '../helper/user/displayName.js'

/**
 * The `@username` worth showing next to a user's name, or null when it would add nothing: no name of
 * their own (stage name or profile name), or a closed account, whose username is an internal
 * `deleted_<uuid>` handle (#1115, #1118). Pass `isDeleted` where the API sends the flag; the name
 * check covers the payloads that do not, and holds because the server names it in the page's locale.
 */
export function userHandle(username, displayName, isDeleted = false) {
  if (
    isDeleted ||
    !displayName ||
    displayName === username ||
    displayName === deletedDisplayName()
  ) {
    return null
  }
  return `@${username}`
}
