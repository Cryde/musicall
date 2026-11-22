import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  activitySentence,
  activitySentenceKey,
  afterLike,
  appendLikersPage,
  appendWallPage,
  COMMENT_ORDERS,
  COMMENT_PAGE_SIZE,
  canPublish,
  commentTree,
  DEFAULT_COMMENT_ORDER,
  emptyPostDraft,
  LIKERS_PAGE_SIZE,
  loadsAbove,
  MAX_POST_LENGTH,
  nextCommentsCursor,
  nextLikersCursor,
  nextWallCursor,
  photoGridClass,
  postAfterCommentCount,
  postItem,
  postPayload,
  showsPosts,
  treeWithComment,
  treeWithCommentLike,
  treeWithNextPage,
  treeWithoutComment,
  WALL_FILTERS,
  WALL_PAGE_SIZE,
  wallAfterPinChange,
  wallFilterFromQuery,
  wallItemKey,
  wallNeedsScrolling,
  wallOutOfView,
  youtubeThumbnailUrl
} from './profileWall.js'

const photo = (name) => new File(['x'], name, { type: 'image/jpeg' })

describe('canPublish', () => {
  it('needs a text, photos or a link', () => {
    assert.equal(canPublish(emptyPostDraft()), false)
    assert.equal(canPublish({ ...emptyPostDraft(), content: '   ' }), false)
    assert.equal(canPublish({ ...emptyPostDraft(), content: 'Concert ce soir' }), true)
    assert.equal(canPublish({ ...emptyPostDraft(), photos: [photo('a.jpg')] }), true)
    assert.equal(
      canPublish({ ...emptyPostDraft(), mediaUrl: 'https://youtu.be/dQw4w9WgXcQ' }),
      true
    )
  })

  it('refuses photos and a link together, and a text over the limit', () => {
    assert.equal(
      canPublish({
        content: '',
        photos: [photo('a.jpg')],
        mediaUrl: 'https://youtu.be/dQw4w9WgXcQ'
      }),
      false
    )
    assert.equal(
      canPublish({ ...emptyPostDraft(), content: 'a'.repeat(MAX_POST_LENGTH + 1) }),
      false
    )
  })
})

describe('postPayload', () => {
  it('sends JSON without photos, the link only when there is one', () => {
    assert.deepEqual(postPayload({ ...emptyPostDraft(), content: 'Salut' }), { content: 'Salut' })
    assert.deepEqual(postPayload({ ...emptyPostDraft(), mediaUrl: ' https://youtu.be/x ' }), {
      content: '',
      media_url: 'https://youtu.be/x'
    })
  })

  it('sends the photos as multipart, in their order', () => {
    const form = postPayload({
      content: 'Forest',
      photos: [photo('1.jpg'), photo('2.jpg')],
      mediaUrl: ''
    })
    assert.ok(form instanceof FormData)
    assert.equal(form.get('content'), 'Forest')
    assert.deepEqual(
      form.getAll('photos[]').map((file) => file.name),
      ['1.jpg', '2.jpg']
    )
  })
})

describe('appendWallPage', () => {
  const line = (type, id) =>
    type === 'post'
      ? { type, post: { id } }
      : { type, [type === 'course' ? 'publication' : type]: { id } }

  it('adds the new page after what is held, without repeating a line', () => {
    assert.deepEqual(
      appendWallPage(
        [line('post', 'a'), line('announce', 'b')],
        [line('announce', 'b'), line('media', 'b'), line('course', 'c')]
      ),
      [line('post', 'a'), line('announce', 'b'), line('media', 'b'), line('course', 'c')]
    )
  })
})

describe('nextWallCursor', () => {
  it('pages like the server', () => {
    // ProfileWallItem::PAGE_SIZE, which ProfileWallTest pins to ten as well.
    assert.equal(WALL_PAGE_SIZE, 10)
  })

  const page = (count) =>
    Array.from({ length: count }, (_, index) => ({ type: 'post', cursor: `post_1_${index}` }))

  it('goes on after the last line of a full page', () => {
    assert.equal(nextWallCursor(page(WALL_PAGE_SIZE)), `post_1_${WALL_PAGE_SIZE - 1}`)
  })

  it('stops after a shorter page, or an empty one', () => {
    assert.equal(nextWallCursor(page(WALL_PAGE_SIZE - 1)), null)
    assert.equal(nextWallCursor([]), null)
  })
})

describe('wallItemKey and activitySentenceKey', () => {
  it('keys a line by its kind and id, and names the sentence of an activity', () => {
    assert.equal(wallItemKey({ type: 'publication', publication: { id: 42 } }), 'publication-42')
    assert.equal(wallItemKey({ type: 'post', post: { id: 'p1' } }), 'post-p1')
    assert.equal(activitySentenceKey({ type: 'course' }), 'profile.wall.activity.course')
    assert.equal(wallItemKey({ type: 'service', service: { id: 's1' } }), 'service-s1')
    assert.equal(activitySentenceKey({ type: 'service' }), 'profile.wall.activity.service')
    assert.equal(activitySentenceKey({ type: 'post' }), null)
  })
})

describe('activitySentence', () => {
  // Writes the key and its parameters back, so the test reads which message is asked for.
  const t = (key, params = {}) => `${key}${JSON.stringify(params)}`

  it('names the service a service line is about: its headline, else its kind', () => {
    assert.equal(
      activitySentence(
        {
          type: 'service',
          service: { kind: 'sound_engineering', headline: 'Mixage et prise de son' }
        },
        t
      ),
      'profile.wall.activity.service{"name":"Mixage et prise de son"}'
    )
    assert.equal(
      activitySentence({ type: 'service', service: { kind: 'teaching', headline: null } }, t),
      'profile.wall.activity.service{"name":"profile.services.kinds.teaching.label{}"}'
    )
  })

  it('leaves the other sentences as they were', () => {
    assert.equal(activitySentence({ type: 'course' }, t), 'profile.wall.activity.course{}')
  })
})

describe('photoGridClass', () => {
  it('gives one column to a single photo and rows of a set height to more', () => {
    assert.equal(photoGridClass(1), 'grid-cols-1')
    assert.equal(photoGridClass(2), 'grid-cols-2 auto-rows-[14rem]')
    assert.equal(photoGridClass(3), 'grid-cols-2 auto-rows-[10rem] sm:auto-rows-[12rem]')
    assert.equal(photoGridClass(0), 'grid-cols-1')
  })
})

describe('youtubeThumbnailUrl', () => {
  it('points at the video still', () => {
    assert.equal(
      youtubeThumbnailUrl('dQw4w9WgXcQ'),
      'https://img.youtube.com/vi/dQw4w9WgXcQ/hqdefault.jpg'
    )
  })
})

describe('wallAfterPinChange', () => {
  const post = (id, day, isPinned = false) => ({
    id,
    creation_datetime: `2026-10-${day}T10:00:00+00:00`,
    is_pinned: isPinned
  })
  const announce = (id, day) => ({
    type: 'announce',
    date: `2026-10-${day}T10:00:00+00:00`,
    announce: { id }
  })
  const lineIds = (items) => items.map((item) => [item.type, (item.post ?? item.announce).id])

  it('moves the post pinned out of the lines and puts the previous one back at its date', () => {
    const wall = {
      pinnedPost: post('old', '05', true),
      items: [
        postItem(post('c', '09')),
        postItem(post('b', '07')),
        announce('x', '05'),
        postItem(post('a', '03'))
      ],
      hasMore: false
    }
    const next = wallAfterPinChange(wall, post('b', '07', true))
    assert.equal(next.pinnedPost.id, 'b')
    // Same second as the announce: a post comes first, as the server orders them.
    assert.deepEqual(lineIds(next.items), [
      ['post', 'c'],
      ['post', 'old'],
      ['announce', 'x'],
      ['post', 'a']
    ])
    assert.equal(next.items[1].post.is_pinned, false)
  })

  it('leaves a released post to a later page when it is older than everything loaded', () => {
    const wall = {
      pinnedPost: post('old', '01', true),
      items: [postItem(post('b', '07'))],
      hasMore: true
    }
    assert.deepEqual(wallAfterPinChange(wall, post('b', '07', true)).items, [])
  })

  it('puts an unpinned post back among the lines', () => {
    const wall = {
      pinnedPost: post('b', '07', true),
      items: [postItem(post('c', '09')), announce('x', '03')],
      hasMore: false
    }
    const next = wallAfterPinChange(wall, post('b', '07'))
    assert.equal(next.pinnedPost, null)
    assert.deepEqual(lineIds(next.items), [
      ['post', 'c'],
      ['post', 'b'],
      ['announce', 'x']
    ])
  })
})

describe('afterLike', () => {
  const post = { id: 'p1', like_count: 3, has_liked: false }

  it('counts a like and takes it back', () => {
    const liked = afterLike(post, true)
    assert.deepEqual(liked, { id: 'p1', like_count: 4, has_liked: true })
    assert.deepEqual(afterLike(liked, false), post)
  })

  it('leaves the count alone when the state does not change', () => {
    assert.equal(afterLike(post, false), post)
  })
})

describe('postAfterCommentCount', () => {
  it('moves the count and never below zero', () => {
    assert.equal(postAfterCommentCount({ comment_count: 2 }, 1).comment_count, 3)
    assert.equal(postAfterCommentCount({ comment_count: 1 }, -3).comment_count, 0)
  })
})

describe('commentTree', () => {
  it('reads a page down to the newest, each reply under its comment, and drops a reply whose comment is missing', () => {
    const tree = commentTree([
      { id: 'b' },
      { id: 'a' },
      { id: 'a1', parent_id: 'a' },
      { id: 'x1', parent_id: 'hidden' },
      { id: 'a2', parent_id: 'a' }
    ])
    assert.deepEqual(tree, [
      {
        id: 'a',
        replies: [
          { id: 'a1', parent_id: 'a' },
          { id: 'a2', parent_id: 'a' }
        ]
      },
      { id: 'b', replies: [] }
    ])
  })
})

describe('commentTree in the oldest order', () => {
  it('keeps the page as it came, each reply under its comment', () => {
    assert.deepEqual(
      commentTree(
        [{ id: 'a' }, { id: 'a1', parent_id: 'a' }, { id: 'b' }, { id: 'x1', parent_id: 'hidden' }],
        'oldest'
      ),
      [
        { id: 'a', replies: [{ id: 'a1', parent_id: 'a' }] },
        { id: 'b', replies: [] }
      ]
    )
  })
})

describe('COMMENT_ORDERS', () => {
  it('mirrors CommentOrder, newest first by default', () => {
    assert.deepEqual(
      COMMENT_ORDERS.map((order) => order.value),
      ['recent', 'oldest', 'liked']
    )
    assert.equal(DEFAULT_COMMENT_ORDER, 'recent')
    assert.equal(loadsAbove('recent'), true)
    assert.equal(loadsAbove('oldest'), false)
  })
})

describe('nextCommentsCursor', () => {
  it('pages like the server', () => {
    // CommentThread::PAGE_SIZE, which ProfilePostCommentPageTest pins to twenty as well.
    assert.equal(COMMENT_PAGE_SIZE, 20)
  })

  const page = (count) =>
    Array.from({ length: count }, (_, index) => [
      { id: `c${index}`, cursor: `recent_1_${index}` },
      { id: `r${index}`, parent_id: `c${index}`, cursor: null }
    ]).flat()

  it('goes on after the last comment of a full page, whatever its replies', () => {
    assert.equal(nextCommentsCursor(page(COMMENT_PAGE_SIZE)), `recent_1_${COMMENT_PAGE_SIZE - 1}`)
  })

  it('goes on after a page reaching down to a notified comment, longer than the others', () => {
    assert.equal(
      nextCommentsCursor(page(COMMENT_PAGE_SIZE + 5)),
      `recent_1_${COMMENT_PAGE_SIZE + 4}`
    )
  })

  it('stops after a shorter page, even with enough replies to fill one, or an empty one', () => {
    assert.equal(nextCommentsCursor(page(COMMENT_PAGE_SIZE - 1)), null)
    assert.equal(nextCommentsCursor([]), null)
  })
})

describe('treeWithNextPage', () => {
  const tree = [
    { id: 'c', replies: [{ id: 'c1', parent_id: 'c' }] },
    { id: 'd', replies: [] }
  ]

  it('puts the older comments of `recent` above, read down to the newest, a comment never twice', () => {
    assert.deepEqual(
      treeWithNextPage(
        tree,
        [{ id: 'c' }, { id: 'b' }, { id: 'a' }, { id: 'a1', parent_id: 'a' }],
        'recent'
      ),
      [
        { id: 'a', replies: [{ id: 'a1', parent_id: 'a' }] },
        { id: 'b', replies: [] },
        { id: 'c', replies: [{ id: 'c1', parent_id: 'c' }] },
        { id: 'd', replies: [] }
      ]
    )
  })

  it('puts the next comments of `oldest` below, in the order they came, a comment never twice', () => {
    assert.deepEqual(
      treeWithNextPage(
        tree,
        [{ id: 'd' }, { id: 'e' }, { id: 'e1', parent_id: 'e' }, { id: 'f' }],
        'oldest'
      ),
      [
        { id: 'c', replies: [{ id: 'c1', parent_id: 'c' }] },
        { id: 'd', replies: [] },
        { id: 'e', replies: [{ id: 'e1', parent_id: 'e' }] },
        { id: 'f', replies: [] }
      ]
    )
  })
})

describe('treeWithCommentLike', () => {
  const tree = [
    {
      id: 'a',
      like_count: 1,
      has_liked: false,
      replies: [{ id: 'a1', parent_id: 'a', like_count: 0, has_liked: false }]
    },
    { id: 'b', like_count: 0, has_liked: false, replies: [] }
  ]

  it('likes a comment, keeping its replies', () => {
    assert.deepEqual(treeWithCommentLike(tree, 'a', true)[0], {
      ...tree[0],
      like_count: 2,
      has_liked: true
    })
  })

  it('likes a reply under its comment, the others untouched', () => {
    const next = treeWithCommentLike(tree, 'a1', true)
    assert.deepEqual(next[0].replies, [
      { id: 'a1', parent_id: 'a', like_count: 1, has_liked: true }
    ])
    assert.equal(next[0].like_count, 1)
    assert.equal(next[1], tree[1])
  })

  it('takes the count the server answered', () => {
    assert.equal(treeWithCommentLike(tree, 'b', true, 5)[1].like_count, 5)
  })

  it('puts a refused like back where it was', () => {
    const liked = treeWithCommentLike(tree, 'a', true)
    assert.deepEqual(treeWithCommentLike(liked, 'a', false), tree)
  })
})

describe('the most liked order', () => {
  it('reads top down, the next page below', () => {
    assert.equal(loadsAbove('liked'), false)
    assert.deepEqual(
      commentTree(
        [{ id: 'popular' }, { id: 'p1', parent_id: 'popular' }, { id: 'quiet' }],
        'liked'
      ),
      [
        { id: 'popular', replies: [{ id: 'p1', parent_id: 'popular' }] },
        { id: 'quiet', replies: [] }
      ]
    )
    assert.deepEqual(
      treeWithNextPage(
        [{ id: 'popular', replies: [] }],
        [{ id: 'popular' }, { id: 'quiet' }],
        'liked'
      ),
      [
        { id: 'popular', replies: [] },
        { id: 'quiet', replies: [] }
      ]
    )
  })
})

describe('nextLikersCursor', () => {
  it('pages like the server', () => {
    // ProfilePostLiker::PAGE_SIZE, which ProfilePostLikeTest pins to twenty as well.
    assert.equal(LIKERS_PAGE_SIZE, 20)
  })

  const page = (count) =>
    Array.from({ length: count }, (_, index) => ({ username: `fan${index}`, cursor: `1_${index}` }))

  it('goes on after the last liker of a full page', () => {
    assert.equal(nextLikersCursor(page(LIKERS_PAGE_SIZE)), `1_${LIKERS_PAGE_SIZE - 1}`)
  })

  it('stops after a shorter page, or an empty one', () => {
    assert.equal(nextLikersCursor(page(LIKERS_PAGE_SIZE - 1)), null)
    assert.equal(nextLikersCursor([]), null)
  })
})

describe('appendLikersPage', () => {
  it('adds the new page after what is held, without repeating a member', () => {
    assert.deepEqual(
      appendLikersPage(
        [{ username: 'a' }, { username: 'b' }],
        [{ username: 'b' }, { username: 'c' }]
      ),
      [{ username: 'a' }, { username: 'b' }, { username: 'c' }]
    )
  })
})

describe('treeWithComment', () => {
  const tree = [{ id: 'a', replies: [] }]

  it('adds a comment last and a reply under its comment', () => {
    assert.deepEqual(treeWithComment(tree, { id: 'b' }), [
      { id: 'a', replies: [] },
      { id: 'b', replies: [] }
    ])
    assert.deepEqual(treeWithComment(tree, { id: 'a1', parent_id: 'a' }), [
      { id: 'a', replies: [{ id: 'a1', parent_id: 'a' }] }
    ])
  })
})

describe('treeWithoutComment', () => {
  const tree = [
    {
      id: 'a',
      replies: [
        { id: 'a1', parent_id: 'a' },
        { id: 'a2', parent_id: 'a' }
      ]
    },
    { id: 'b', replies: [] }
  ]

  it('removes a comment with its replies', () => {
    assert.deepEqual(treeWithoutComment(tree, 'a'), {
      tree: [{ id: 'b', replies: [] }],
      removedCount: 3
    })
  })

  it('removes a single reply', () => {
    assert.deepEqual(treeWithoutComment(tree, 'a1'), {
      tree: [
        { id: 'a', replies: [{ id: 'a2', parent_id: 'a' }] },
        { id: 'b', replies: [] }
      ],
      removedCount: 1
    })
  })

  it('removes nothing for an unknown id', () => {
    assert.equal(treeWithoutComment(tree, 'z').removedCount, 0)
  })
})

describe('wallFilterFromQuery', () => {
  it('reads the filter a link asks for', () => {
    assert.equal(wallFilterFromQuery('medias').type, 'media')
    assert.equal(wallFilterFromQuery('cours').type, 'course')
    assert.equal(wallFilterFromQuery('services').type, 'service')
  })

  it('falls back to « Tout » for nothing or anything it does not know', () => {
    assert.equal(wallFilterFromQuery(undefined), WALL_FILTERS[0])
    assert.equal(wallFilterFromQuery('photos'), WALL_FILTERS[0])
    assert.equal(wallFilterFromQuery(['medias']), WALL_FILTERS[0])
  })
})

describe('showsPosts', () => {
  it('is true for « Tout » and « Posts » only', () => {
    assert.deepEqual(
      WALL_FILTERS.filter(showsPosts).map((filter) => filter.query),
      [null, 'posts']
    )
  })
})

describe('wallNeedsScrolling', () => {
  it('brings the wall into view only while its top is below the middle of the screen', () => {
    assert.equal(wallNeedsScrolling(700, 844), true)
    assert.equal(wallNeedsScrolling(300, 844), false)
    assert.equal(wallNeedsScrolling(-200, 844), false)
  })
})

describe('wallOutOfView', () => {
  it('scrolls to the wall from below it too, as from « Écouter » under the wall on a phone', () => {
    assert.equal(wallOutOfView(-727, 844), true)
    assert.equal(wallOutOfView(700, 844), true)
  })

  it('leaves a wall already starting in the upper half of the screen', () => {
    assert.equal(wallOutOfView(0, 844), false)
    assert.equal(wallOutOfView(300, 844), false)
  })
})
