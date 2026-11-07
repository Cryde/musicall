/** Mirrors ProfilePostCreate on the server (#1316). */
export const MAX_POST_PHOTOS = 4
export const MAX_POST_LENGTH = 2000
/** Mirrors ProfilePostComment::MAX_LENGTH. */
export const MAX_COMMENT_LENGTH = 1000

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

/** The payload a wall line carries: its post, announce, publication or media. */
function payloadOf(item) {
  return item.post ?? item.announce ?? item.publication ?? item.media
}

/** A key unique across the wall: ids only stay unique within one kind of line. */
export function wallItemKey(item) {
  return `${item.type}-${payloadOf(item).id}`
}

/** A post as a wall line, the way the server sends it. */
export function postItem(post) {
  return { type: 'post', date: post.creation_datetime, post }
}

/**
 * The wall's filters (#1317): `query` is the page's `?voir=`, `type` the API's `type`. « Tout » has neither, and
 * every filter but « Tout » has its own empty state.
 */
export const WALL_FILTERS = [
  { query: null, type: null, labelKey: 'profile.wall.filter.all', emptyKey: null },
  {
    query: 'posts',
    type: 'post',
    labelKey: 'profile.wall.filter.posts',
    emptyKey: 'profile.wall.empty.posts'
  },
  {
    query: 'medias',
    type: 'media',
    labelKey: 'profile.wall.filter.media',
    emptyKey: 'profile.wall.empty.media'
  },
  {
    query: 'annonces',
    type: 'announce',
    labelKey: 'profile.wall.filter.announces',
    emptyKey: 'profile.wall.empty.announces'
  },
  {
    query: 'cours',
    type: 'course',
    labelKey: 'profile.wall.filter.courses',
    emptyKey: 'profile.wall.empty.courses'
  }
]

/** The filter a `?voir=` asks for; anything else is « Tout ». */
export function wallFilterFromQuery(value) {
  return (
    WALL_FILTERS.find((filter) => filter.query !== null && filter.query === value) ??
    WALL_FILTERS[0]
  )
}

/** Whether a filter shows posts, so the pinned one, the composer and a post just written. */
export function showsPosts(filter) {
  return filter.type === null || filter.type === 'post'
}

// Mirrors ProfileWallItem::PAGE_SIZE: a page shorter than this is the last one.
export const WALL_PAGE_SIZE = 10

/**
 * Where the page after this one starts: its last line's cursor, or null after a shorter page. When the lines
 * end exactly on a full page, one more fetch comes back empty. Kept apart from the lines, which change at their
 * tail when a post is deleted or unpinned here.
 */
export function nextWallCursor(page) {
  return page.length >= WALL_PAGE_SIZE ? page[page.length - 1].cursor : null
}

/** The lines held so far, followed by a new page, a line never twice. */
export function appendWallPage(items, page) {
  const held = new Set(items.map(wallItemKey))
  return [...items, ...page.filter((item) => !held.has(wallItemKey(item)))]
}

const ACTIVITY_SENTENCE_KEYS = {
  announce: 'profile.wall.activity.announce',
  publication: 'profile.wall.activity.publication',
  course: 'profile.wall.activity.course',
  media: 'profile.wall.activity.media'
}

/** The sentence an activity line opens with, as a full i18n key. */
export function activitySentenceKey(item) {
  return ACTIVITY_SENTENCE_KEYS[item.type] ?? null
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
 * The wall after a post's pin changed, without fetching it again: the post pinned leaves the lines, the one it
 * replaces goes back in date order when it falls within the pages already loaded.
 */
export function wallAfterPinChange(wall, post) {
  const others = wall.items.filter((item) => !(item.type === 'post' && item.post.id === post.id))
  if (!post.is_pinned) {
    return { pinnedPost: null, items: insertByDate(others, postItem(post), wall.hasMore) }
  }
  const released =
    wall.pinnedPost && wall.pinnedPost.id !== post.id
      ? postItem({ ...wall.pinnedPost, is_pinned: false })
      : null
  return {
    pinnedPost: post,
    items: released ? insertByDate(others, released, wall.hasMore) : others
  }
}

// Older than every line loaded while more remain: a later page brings it, at its place.
function insertByDate(items, item, hasMore) {
  const index = items.findIndex((held) => isNewer(item, held))
  if (index === -1) {
    return hasMore ? items : [...items, item]
  }
  return [...items.slice(0, index), item, ...items.slice(index)]
}

// The server's order: the date, then a post before any other line, then the id, newest first. Its dates share
// one offset, so they compare as text.
function isNewer(postLine, held) {
  if (postLine.date !== held.date) {
    return postLine.date > held.date
  }
  return held.type !== 'post' || postLine.post.id > held.post.id
}

/** A post as it reads once the viewer liked it or took the like back: the count moves only when the state does. */
export function postAfterLike(post, liked) {
  if (post.has_liked === liked) {
    return post
  }
  return { ...post, has_liked: liked, like_count: Math.max(0, post.like_count + (liked ? 1 : -1)) }
}

/** A post whose comment count moved by `delta`. */
export function postAfterCommentCount(post, delta) {
  return { ...post, comment_count: Math.max(0, post.comment_count + delta) }
}

/**
 * The server's flat list, oldest first, as roots each holding its replies. A reply whose comment is not in the
 * list has nothing to answer on screen, so it is left out.
 */
export function commentTree(comments) {
  const roots = comments
    .filter((comment) => !comment.parent_id)
    .map((comment) => ({ ...comment, replies: [] }))
  const byId = new Map(roots.map((root) => [root.id, root]))
  for (const comment of comments) {
    if (comment.parent_id) {
      byId.get(comment.parent_id)?.replies.push(comment)
    }
  }
  return roots
}

/** The tree with a new comment: a root goes last, a reply goes last under its comment. */
export function treeWithComment(tree, comment) {
  if (!comment.parent_id) {
    return [...tree, { ...comment, replies: [] }]
  }
  return tree.map((root) =>
    root.id === comment.parent_id ? { ...root, replies: [...root.replies, comment] } : root
  )
}

/** The tree without a comment, and how many went with it: a root takes its replies along. */
export function treeWithoutComment(tree, id) {
  const root = tree.find((held) => held.id === id)
  if (root) {
    return { tree: tree.filter((held) => held.id !== id), removedCount: 1 + root.replies.length }
  }
  let removedCount = 0
  const next = tree.map((held) => {
    const replies = held.replies.filter((reply) => reply.id !== id)
    removedCount += held.replies.length - replies.length
    return replies.length === held.replies.length ? held : { ...held, replies }
  })
  return { tree: next, removedCount }
}
