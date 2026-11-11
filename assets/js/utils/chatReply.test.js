import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  conversationAuthor,
  replyQuoteFromMessage,
  replyQuoteText,
  withQuotesRefreshed
} from './chatReply.js'

/** What a reply quote says, in the banner and in the bubble (#1377). Run with `npm test`. */

const t = (key) => `[${key}]`

function quote(overrides = {}) {
  return {
    id: 'message-1',
    author_id: 'user-alice',
    author_display_name: 'Alice',
    excerpt: '',
    has_image: false,
    has_voice_note: false,
    is_deleted: false,
    ...overrides
  }
}

/** A message of a conversation (#1380): the author is the embedded user, not flat fields. */
function conversationMessage(overrides = {}) {
  return {
    id: 'message-1',
    author: { id: 'user-lea', username: 'lea', display_name: 'Léa Martin' },
    content: 'On se voit samedi ?',
    is_deleted: false,
    ...overrides
  }
}

function message(overrides = {}) {
  return {
    id: 'message-1',
    author_id: 'user-alice',
    author_display_name: 'Alice',
    content: 'On répète samedi à 14h ?',
    image: null,
    voice_note: null,
    is_deleted: false,
    ...overrides
  }
}

describe('replyQuoteText', () => {
  it('shows the excerpt of a message with text', () => {
    assert.equal(replyQuoteText(quote({ excerpt: 'On répète ?' }), t), 'On répète ?')
  })

  it('keeps the excerpt of a photo sent with a caption', () => {
    assert.equal(replyQuoteText(quote({ excerpt: 'la scène', has_image: true }), t), 'la scène')
  })

  it('names a photo, a voice note and attachments sent without text', () => {
    assert.equal(replyQuoteText(quote({ has_image: true }), t), '[band_space_chat.reply.photo]')
    assert.equal(
      replyQuoteText(quote({ has_voice_note: true }), t),
      '[band_space_chat.reply.voice_note]'
    )
    assert.equal(replyQuoteText(quote(), t), '[band_space_chat.reply.attachment]')
  })

  it('says a deleted message is deleted, whatever it carried', () => {
    assert.equal(
      replyQuoteText(quote({ is_deleted: true, excerpt: 'reste', has_image: true }), t),
      '[band_space_chat.reply.deleted]'
    )
  })
})

describe('replyQuoteFromMessage', () => {
  it('quotes a message in the shape the API sends', () => {
    assert.deepEqual(replyQuoteFromMessage(message()), {
      id: 'message-1',
      author_id: 'user-alice',
      author_display_name: 'Alice',
      excerpt: 'On répète samedi à 14h ?',
      has_image: false,
      has_voice_note: false,
      is_deleted: false
    })
  })

  it('reads the rendered content as one line of text, mentions as names', () => {
    const quoted = replyQuoteFromMessage(
      message({
        content: '<span class="chat-mention">@bob</span> l&#039;ampli<br />\nest réparé'
      })
    )

    assert.equal(quoted.excerpt, "@bob l'ampli est réparé")
  })

  it('cuts a long message at 140 characters, the last one an ellipsis', () => {
    const quoted = replyQuoteFromMessage(message({ content: 'a'.repeat(200) }))

    assert.equal(quoted.excerpt.length, 140)
    assert.equal(quoted.excerpt, `${'a'.repeat(139)}…`)
  })

  it('empties a deleted message, as the server does', () => {
    const quoted = replyQuoteFromMessage(
      message({ is_deleted: true, image: { file_id: 'f1', is_available: true } })
    )

    assert.equal(quoted.excerpt, '')
    assert.equal(quoted.has_image, false)
    assert.equal(quoted.is_deleted, true)
  })

  it('names the author of a conversation message the way the conversation does', () => {
    assert.deepEqual(replyQuoteFromMessage(conversationMessage(), conversationAuthor), {
      id: 'message-1',
      author_id: 'user-lea',
      author_display_name: 'Léa Martin',
      excerpt: 'On se voit samedi ?',
      has_image: false,
      has_voice_note: false,
      is_deleted: false
    })
  })

  it('flags the media of a message without text', () => {
    const photo = replyQuoteFromMessage(
      message({ content: '', image: { file_id: 'f1', is_available: true } })
    )
    const voiceNote = replyQuoteFromMessage(
      message({ content: '', voice_note: { file_id: 'f2', duration_seconds: 4 } })
    )

    assert.equal(photo.has_image, true)
    assert.equal(photo.excerpt, '')
    assert.equal(voiceNote.has_voice_note, true)
  })
})

describe('withQuotesRefreshed', () => {
  it('rebuilds the quotes of the replies to a message just deleted or edited', () => {
    const reply = { id: 'reply-1', reply_to: quote({ excerpt: 'On répète samedi ?' }) }
    const other = { id: 'reply-2', reply_to: quote({ id: 'message-9', excerpt: 'Autre' }) }
    const plain = { id: 'plain', reply_to: null }

    const [refreshed, untouched, unchanged] = withQuotesRefreshed(
      [reply, other, plain],
      [message({ content: '', is_deleted: true })]
    )

    assert.equal(refreshed.reply_to.is_deleted, true)
    assert.equal(refreshed.reply_to.excerpt, '')
    assert.equal(untouched, other)
    assert.equal(unchanged, plain)
  })

  it('takes the new text of an edited message', () => {
    const [refreshed] = withQuotesRefreshed(
      [{ id: 'reply-1', reply_to: quote({ excerpt: 'On répète samedi ?' }) }],
      [message({ content: 'On répète dimanche ?' })]
    )

    assert.equal(refreshed.reply_to.excerpt, 'On répète dimanche ?')
  })

  it('quotes a conversation message with its embedded author', () => {
    const [refreshed] = withQuotesRefreshed(
      [
        {
          id: 'reply-1',
          reply_to: quote({ author_id: 'user-lea', excerpt: 'On se voit samedi ?' })
        }
      ],
      [conversationMessage({ content: 'On se voit dimanche ?' })],
      conversationAuthor
    )

    assert.equal(refreshed.reply_to.excerpt, 'On se voit dimanche ?')
    assert.equal(refreshed.reply_to.author_display_name, 'Léa Martin')
  })
})
