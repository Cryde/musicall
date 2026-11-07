import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  activitySentenceKey,
  appendWallPage,
  canPublish,
  commentTree,
  emptyPostDraft,
  MAX_POST_LENGTH,
  nextWallCursor,
  photoGridClass,
  postAfterCommentCount,
  postAfterLike,
  postItem,
  postPayload,
  showsPosts,
  treeWithComment,
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
    assert.equal(activitySentenceKey({ type: 'post' }), null)
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

describe('postAfterLike', () => {
  const post = { id: 'p1', like_count: 3, has_liked: false }

  it('counts a like and takes it back', () => {
    const liked = postAfterLike(post, true)
    assert.deepEqual(liked, { id: 'p1', like_count: 4, has_liked: true })
    assert.deepEqual(postAfterLike(liked, false), post)
  })

  it('leaves the count alone when the state does not change', () => {
    assert.equal(postAfterLike(post, false), post)
  })
})

describe('postAfterCommentCount', () => {
  it('moves the count and never below zero', () => {
    assert.equal(postAfterCommentCount({ comment_count: 2 }, 1).comment_count, 3)
    assert.equal(postAfterCommentCount({ comment_count: 1 }, -3).comment_count, 0)
  })
})

describe('commentTree', () => {
  it('puts each reply under its comment, in order, and drops a reply whose comment is missing', () => {
    const tree = commentTree([
      { id: 'a' },
      { id: 'a1', parent_id: 'a' },
      { id: 'b' },
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
