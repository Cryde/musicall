import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  appendWallPage,
  canPublish,
  commentTree,
  emptyPostDraft,
  MAX_POST_LENGTH,
  photoGridClass,
  postAfterCommentCount,
  postAfterLike,
  postPayload,
  treeWithComment,
  treeWithoutComment,
  wallAfterPinChange,
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
  it('adds the new page after what is held, without repeating a post', () => {
    assert.deepEqual(appendWallPage([{ id: 'a' }, { id: 'b' }], [{ id: 'b' }, { id: 'c' }]), [
      { id: 'a' },
      { id: 'b' },
      { id: 'c' }
    ])
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

  it('moves the post pinned out of the list and puts the previous one back at its date', () => {
    const wall = {
      pinnedPost: post('old', '05', true),
      posts: [post('c', '09'), post('b', '07'), post('a', '03')],
      hasMore: false
    }
    const next = wallAfterPinChange(wall, post('b', '07', true))
    assert.equal(next.pinnedPost.id, 'b')
    assert.deepEqual(
      next.posts.map((held) => [held.id, held.is_pinned]),
      [
        ['c', false],
        ['old', false],
        ['a', false]
      ]
    )
  })

  it('leaves a released post to a later page when it is older than everything loaded', () => {
    const wall = { pinnedPost: post('old', '01', true), posts: [post('b', '07')], hasMore: true }
    assert.deepEqual(
      wallAfterPinChange(wall, post('b', '07', true)).posts.map((held) => held.id),
      []
    )
  })

  it('puts an unpinned post back in the list', () => {
    const wall = {
      pinnedPost: post('b', '07', true),
      posts: [post('c', '09'), post('a', '03')],
      hasMore: false
    }
    const next = wallAfterPinChange(wall, post('b', '07'))
    assert.equal(next.pinnedPost, null)
    assert.deepEqual(
      next.posts.map((held) => held.id),
      ['c', 'b', 'a']
    )
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
