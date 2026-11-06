/** Mirrors ProfilePostCreate on the server (#1316). */
export const MAX_POST_PHOTOS = 4
export const MAX_POST_LENGTH = 2000

/** A post the composer starts from. */
export function emptyPostDraft() {
  return { content: '', photos: [], mediaUrl: '' }
}

/** Whether the draft can be sent: something in it, never photos and a link together, not too long. */
export function canPublish(draft) {
  const hasText = draft.content.trim() !== ''
  const hasPhotos = draft.photos.length > 0
  const hasLink = draft.mediaUrl.trim() !== ''
  return (
    (hasText || hasPhotos || hasLink) &&
    !(hasPhotos && hasLink) &&
    draft.photos.length <= MAX_POST_PHOTOS &&
    draft.content.length <= MAX_POST_LENGTH
  )
}

/** Multipart only when photos travel with it, JSON otherwise. */
export function postPayload(draft) {
  if (draft.photos.length === 0) {
    const mediaUrl = draft.mediaUrl.trim()
    return { content: draft.content, ...(mediaUrl ? { media_url: mediaUrl } : {}) }
  }
  const form = new FormData()
  form.append('content', draft.content)
  for (const photo of draft.photos) {
    form.append('photos[]', photo)
  }
  return form
}

/** The posts held so far, followed by a new page, a post never twice. */
export function appendWallPage(posts, page) {
  const held = new Set(posts.map((post) => post.id))
  return [...posts, ...page.filter((post) => !held.has(post.id))]
}

const PHOTO_GRIDS = {
  1: 'grid-cols-1',
  2: 'grid-cols-2 auto-rows-[14rem]',
  3: 'grid-cols-2 auto-rows-[10rem] sm:auto-rows-[12rem]',
  4: 'grid-cols-2 auto-rows-[10rem] sm:auto-rows-[12rem]'
}

/**
 * The grid that holds a post's photos: one photo keeps its shape, several share rows of a set height so the
 * first of three can span two. Full class names, so Tailwind finds them.
 */
export function photoGridClass(count) {
  return PHOTO_GRIDS[count] ?? PHOTO_GRIDS[1]
}

/** The YouTube still a post shows until it is played. */
export function youtubeThumbnailUrl(embedId) {
  return `https://img.youtube.com/vi/${encodeURIComponent(embedId)}/hqdefault.jpg`
}

/**
 * The wall after a post's pin changed, without fetching it again: the post pinned leaves the list, the one it
 * replaces goes back in date order when it falls within the pages already loaded.
 */
export function wallAfterPinChange(wall, post) {
  const others = wall.posts.filter((held) => held.id !== post.id)
  if (!post.is_pinned) {
    return { pinnedPost: null, posts: insertByDate(others, post, wall.hasMore) }
  }
  const released =
    wall.pinnedPost && wall.pinnedPost.id !== post.id
      ? { ...wall.pinnedPost, is_pinned: false }
      : null
  return {
    pinnedPost: post,
    posts: released ? insertByDate(others, released, wall.hasMore) : others
  }
}

// Older than every post loaded while more remain: a later page brings it, at its place.
function insertByDate(posts, post, hasMore) {
  const index = posts.findIndex((held) => isNewer(post, held))
  if (index === -1) {
    return hasMore ? posts : [...posts, post]
  }
  return [...posts.slice(0, index), post, ...posts.slice(index)]
}

// The server's order: creation date, then id, newest first. Its dates share one offset, so they compare as text.
function isNewer(a, b) {
  return (
    a.creation_datetime > b.creation_datetime ||
    (a.creation_datetime === b.creation_datetime && a.id > b.id)
  )
}
