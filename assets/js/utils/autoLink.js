/**
 * Wraps bare URLs in anchors, for message bodies rendered with `v-html`.
 *
 * The input is always sanitizer output from the API, never raw user input: the server strips every
 * tag but `<br>` and escapes the rest, which is what makes this safe to inject. `"` and `=` come back
 * as entities, so a URL cannot break out of the `href` this builds, and the pattern only matches
 * `http` and `https`, so no `javascript:` scheme can reach it either. See #956, closed on the point
 * that sanitizing at read time is what keeps that guarantee.
 *
 * `[^\s<]` stops the match at a tag boundary, so a URL at the end of a line is not swallowed into the
 * `<br>` that follows it.
 */
const URL_PATTERN = /(https?:\/\/[^\s<]+)/g

export function autoLink(text) {
  if (!text) {
    return ''
  }

  return text.replace(URL_PATTERN, '<a href="$1" target="_blank" rel="noopener">$1</a>')
}
