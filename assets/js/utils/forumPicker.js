/**
 * The forums offered when a topic is started outside a forum (#1080), grouped by category as the API
 * lists them. Any forum takes a new topic, but a category without one would show a bare header.
 */
export function forumPickerGroups(categories) {
  return categories.filter((category) => category.forums.length > 0)
}
