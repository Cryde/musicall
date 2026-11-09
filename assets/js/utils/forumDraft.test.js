import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { MAX_DRAFT_TEXT_LENGTH } from './draftText.js'
import {
  FORUM_REPLY,
  FORUM_TOPIC_CREATE,
  isBlankEditorHtml,
  readReplyDraft,
  readTopicDraft,
  replyAvailability,
  replyDraftPayload,
  topicCreateAvailability,
  topicDraftPayload
} from './forumDraft.js'
import { keepPendingAction, readPendingAction } from './pendingAction.js'

/**
 * A topic or a reply a visitor wrote has to come back after sign-in on the same forum or topic, and has to be dropped
 * with a reason when the forum is gone or the topic gone or locked. Run with `npm test`.
 */

const TOPIC = { slug: 'mon-ampli-grésille', is_locked: false }
const HTML = '<p>Mon ampli <strong>grésille</strong> depuis hier.</p>'
const TOO_LONG = 'a'.repeat(MAX_DRAFT_TEXT_LENGTH + 1)

function fakeStorage() {
  const values = {}

  return {
    getItem: (key) => (key in values ? values[key] : null),
    setItem: (key, value) => {
      values[key] = value
    },
    removeItem: (key) => {
      delete values[key]
    }
  }
}

function throughStorage(kind, payload) {
  const storage = fakeStorage()
  keepPendingAction(
    storage,
    { kind, route: '/forums/guitare', payload, stoppedOnSubmit: true },
    { isVisitor: true }
  )

  return readPendingAction(storage).payload
}

describe('topicDraftPayload', () => {
  it('keeps the forum, the title and the message, and comes back the same', () => {
    const payload = topicDraftPayload({
      forumSlug: 'guitare',
      title: 'Ampli qui grésille',
      html: HTML
    })

    assert.deepEqual(payload, { forumSlug: 'guitare', title: 'Ampli qui grésille', html: HTML })
    assert.deepEqual(readTopicDraft(throughStorage(FORUM_TOPIC_CREATE, payload)), payload)
  })

  it('keeps nothing when the message is too long to keep', () => {
    assert.equal(topicDraftPayload({ forumSlug: 'guitare', title: 'Titre', html: TOO_LONG }), null)
  })
  it('keeps nothing when nothing was typed, so the prompt promises nothing', () => {
    assert.equal(topicDraftPayload({ forumSlug: 'guitare', title: '', html: '<p></p>' }), null)
    assert.equal(topicDraftPayload({ forumSlug: 'guitare', title: '   ', html: '' }), null)
  })

  it('keeps a topic with only its title or only its message written so far', () => {
    assert.deepEqual(topicDraftPayload({ forumSlug: 'guitare', title: 'Titre', html: '<p></p>' }), {
      forumSlug: 'guitare',
      title: 'Titre',
      html: '<p></p>'
    })
    assert.deepEqual(topicDraftPayload({ forumSlug: 'guitare', title: '', html: HTML }), {
      forumSlug: 'guitare',
      title: '',
      html: HTML
    })
  })
})

describe('readTopicDraft', () => {
  it('refuses a payload that cannot be a topic', () => {
    for (const payload of [
      null,
      'texte',
      {},
      { forumSlug: '', title: 'Titre', html: HTML },
      { forumSlug: 'guitare', title: 42, html: HTML },
      { forumSlug: 'guitare', title: 'Titre', html: null },
      { forumSlug: 'guitare', title: TOO_LONG, html: HTML }
    ]) {
      assert.equal(readTopicDraft(payload), null, JSON.stringify(payload))
    }
  })

  it('leaves out anything else that was stored with it', () => {
    assert.deepEqual(
      readTopicDraft({ forumSlug: 'guitare', title: 'Titre', html: HTML, author: 'jeanne' }),
      { forumSlug: 'guitare', title: 'Titre', html: HTML }
    )
  })
})

describe('replyDraftPayload', () => {
  it('keeps the topic and the message, and comes back the same', () => {
    const payload = replyDraftPayload({ topicSlug: TOPIC.slug, html: HTML })

    assert.deepEqual(payload, { topicSlug: TOPIC.slug, html: HTML })
    assert.deepEqual(readReplyDraft(throughStorage(FORUM_REPLY, payload)), payload)
  })

  it('keeps nothing when the message is too long to keep', () => {
    assert.equal(replyDraftPayload({ topicSlug: TOPIC.slug, html: TOO_LONG }), null)
  })
  it('keeps nothing when nothing was typed, so the prompt promises nothing', () => {
    assert.equal(replyDraftPayload({ topicSlug: TOPIC.slug, html: '<p></p>' }), null)
  })
})

describe('isBlankEditorHtml', () => {
  it('sees an empty editor, and one cleared by hand, as blank', () => {
    for (const html of [
      '',
      '<p></p>',
      '<p></p><p></p>',
      '<p> </p>',
      '<p><br></p>',
      '<p>&nbsp;</p>'
    ]) {
      assert.equal(isBlankEditorHtml(html), true, html)
    }
  })

  it('sees text, an image or a video as content', () => {
    for (const html of [
      HTML,
      '<p>a</p>',
      '<img src="https://musicall.com/image.webp">',
      '<div data-youtube-video=""><iframe src="https://www.youtube-nocookie.com/embed/abc"></iframe></div>'
    ]) {
      assert.equal(isBlankEditorHtml(html), false, html)
    }
  })
})

describe('readReplyDraft', () => {
  it('refuses a payload that cannot be a reply', () => {
    for (const payload of [
      null,
      [],
      { html: HTML },
      { topicSlug: 7, html: HTML },
      { topicSlug: TOPIC.slug, html: ['<p>a</p>'] }
    ]) {
      assert.equal(readReplyDraft(payload), null, JSON.stringify(payload))
    }
  })
})

describe('topicCreateAvailability', () => {
  it('is available on the forum it was written in', () => {
    assert.equal(
      topicCreateAvailability({
        forum: { id: 'f1' },
        forumSlug: 'guitare',
        pageForumSlug: 'guitare'
      }),
      'available'
    )
  })

  it('says the forum is gone when it no longer loads', () => {
    assert.equal(
      topicCreateAvailability({ forum: null, forumSlug: 'guitare', pageForumSlug: 'guitare' }),
      'forum_gone'
    )
  })

  it('does not reopen a topic written for another forum, or a draft that could not be read', () => {
    assert.equal(
      topicCreateAvailability({
        forum: { id: 'f1' },
        forumSlug: 'basse',
        pageForumSlug: 'guitare'
      }),
      'forum_gone'
    )
    assert.equal(
      topicCreateAvailability({
        forum: { id: 'f1' },
        forumSlug: undefined,
        pageForumSlug: 'guitare'
      }),
      'forum_gone'
    )
  })
})

describe('replyAvailability', () => {
  it('is available on an open topic', () => {
    assert.equal(replyAvailability({ topic: TOPIC, topicSlug: TOPIC.slug }), 'available')
  })

  it('says the topic was locked in the meantime', () => {
    assert.equal(
      replyAvailability({ topic: { ...TOPIC, is_locked: true }, topicSlug: TOPIC.slug }),
      'topic_locked'
    )
  })

  it('says the topic is gone when it no longer loads, or is another one', () => {
    assert.equal(replyAvailability({ topic: null, topicSlug: TOPIC.slug }), 'topic_gone')
    assert.equal(replyAvailability({ topic: TOPIC, topicSlug: 'autre-sujet' }), 'topic_gone')
    assert.equal(replyAvailability({ topic: TOPIC, topicSlug: undefined }), 'topic_gone')
  })
})
