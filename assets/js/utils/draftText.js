/**
 * The longest text a visitor's draft keeps over sign-in (#1305). localStorage is a few megabytes for the whole site,
 * so a pasted novel is not kept, and the sign-in prompt then promises nothing.
 */
export const MAX_DRAFT_TEXT_LENGTH = 50_000

export function isKeepableText(value) {
  return typeof value === 'string' && value.length <= MAX_DRAFT_TEXT_LENGTH
}
