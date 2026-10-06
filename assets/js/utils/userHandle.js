import { DELETED_DISPLAY_NAME } from '../helper/user/displayName.js'

/**
 * The `@username` worth showing next to a user's name, or null when it would add nothing: no name of
 * their own (stage name or profile name), or a closed account, whose username is an internal
 * `deleted_<uuid>` handle (#1115, #1118).
 */
export function userHandle(username, displayName) {
  if (!displayName || displayName === username || displayName === DELETED_DISPLAY_NAME) {
    return null
  }
  return `@${username}`
}
