import { DELETED_DISPLAY_NAME } from '../helper/user/displayName.js'

/**
 * The `@username` worth showing next to a band member's name, or null when it would add nothing: no
 * stage name, or a closed account, whose username is an internal `deleted_<uuid>` handle (#1115).
 */
export function memberHandle(username, displayName) {
  if (!displayName || displayName === username || displayName === DELETED_DISPLAY_NAME) {
    return null
  }
  return `@${username}`
}
