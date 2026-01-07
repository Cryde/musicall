/**
 * Generate a consistent background color from a username.
 * Uses a simple hash to always produce the same color for the same username.
 */

// Pleasant color palette for avatars (good contrast with white text)
const AVATAR_COLORS = [
  '#6366f1', // indigo
  '#8b5cf6', // violet
  '#a855f7', // purple
  '#d946ef', // fuchsia
  '#ec4899', // pink
  '#f43f5e', // rose
  '#ef4444', // red
  '#f97316', // orange
  '#f59e0b', // amber
  '#84cc16', // lime
  '#22c55e', // green
  '#14b8a6', // teal
  '#06b6d4', // cyan
  '#0ea5e9', // sky
  '#3b82f6', // blue
]

/**
 * Simple hash function to convert a string to a number
 */
function hashString(str) {
  let hash = 0
  for (let i = 0; i < str.length; i++) {
    const char = str.charCodeAt(i)
    hash = ((hash << 5) - hash) + char
    hash = hash & hash // Convert to 32bit integer
  }
  return Math.abs(hash)
}

/**
 * Get a consistent color for a username
 * @param {string} username - The username to generate a color for
 * @returns {string} A hex color code
 */
export function getAvatarColor(username) {
  if (!username) return AVATAR_COLORS[0]
  const hash = hashString(username.toLowerCase())
  return AVATAR_COLORS[hash % AVATAR_COLORS.length]
}

/**
 * Get style object for Avatar component without profile picture
 * @param {string} username - The username to generate styles for
 * @returns {object} Style object with background color and text color
 */
export function getAvatarStyle(username) {
  return {
    backgroundColor: getAvatarColor(username),
    color: 'white'
  }
}
