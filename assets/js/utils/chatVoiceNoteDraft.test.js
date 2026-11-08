import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { createVoiceNoteSender } from './chatVoiceNoteDraft.js'

/**
 * The composer's failed voice note (#1057): kept on a failure, resent as the very same file, gone
 * once it goes out or is discarded, its playback URL revoked with it. Run with `npm test`.
 */

const recording = new Blob(['sound'], { type: 'audio/webm;codecs=opus' })

/** Object URLs that only exist in the test: numbered as they are created, recorded as revoked. */
function fakeObjectUrls() {
  const created = []
  const revoked = []

  return {
    create(blob) {
      created.push(blob)
      return `blob:test/${created.length}`
    },
    revoke(url) {
      revoked.push(url)
    },
    created,
    revoked
  }
}

/**
 * @param {Array<boolean|Promise<boolean>>} answers what each successive send answers
 */
function build(answers) {
  const delivered = []
  const draftChanges = []
  const urls = fakeObjectUrls()
  const sender = createVoiceNoteSender({
    deliver: async (file) => {
      delivered.push(file)
      return answers.shift()
    },
    onDraftChange: (draft) => draftChanges.push(draft),
    urls
  })

  return {
    sender,
    delivered,
    draftChanges,
    urls,
    get draft() {
      return draftChanges.at(-1) ?? null
    }
  }
}

describe('createVoiceNoteSender', () => {
  it('sends the recording as a named file and keeps nothing once it went out', async () => {
    const { sender, delivered, draftChanges, urls } = build([true])

    assert.equal(await sender.send(recording, 42), true)

    assert.equal(delivered.length, 1)
    assert.equal(delivered[0].name, 'note-vocale.webm')
    assert.equal(delivered[0].type, 'audio/webm;codecs=opus')
    assert.deepEqual(draftChanges, [])
    assert.deepEqual(urls.created, [])
  })

  it('keeps a note that failed to send, with its duration and a URL to listen to it', async () => {
    const built = build([false])

    assert.equal(await built.sender.send(recording, 42), false)

    assert.deepEqual(built.draft, {
      file: built.delivered[0],
      durationSeconds: 42,
      playbackUrl: 'blob:test/1'
    })
    assert.deepEqual(built.urls.created, [built.delivered[0]])
  })

  it('resends exactly the same file, then clears the draft and revokes its URL', async () => {
    const built = build([false, true])
    await built.sender.send(recording, 42)

    assert.equal(await built.sender.resend(), true)

    assert.equal(built.delivered.length, 2)
    assert.equal(built.delivered[1], built.delivered[0])
    assert.equal(built.draft, null)
    assert.deepEqual(built.urls.revoked, ['blob:test/1'])
  })

  it('keeps the draft and its URL when the resend fails too', async () => {
    const built = build([false, false])
    await built.sender.send(recording, 42)
    const kept = built.draft

    assert.equal(await built.sender.resend(), false)

    assert.equal(built.draft, kept)
    assert.equal(built.urls.created.length, 1)
    assert.deepEqual(built.urls.revoked, [])
  })

  it('discards the draft and revokes its URL', async () => {
    const built = build([false])
    await built.sender.send(recording, 42)

    built.sender.discard()

    assert.equal(built.draft, null)
    assert.deepEqual(built.urls.revoked, ['blob:test/1'])
    assert.equal(await built.sender.resend(), false)
    assert.equal(built.delivered.length, 1)
  })

  it('uploads once when retried twice while the first retry is in flight', async () => {
    let answer
    const built = build([false, new Promise((resolve) => (answer = resolve))])
    await built.sender.send(recording, 42)

    const first = built.sender.resend()
    assert.equal(await built.sender.resend(), false)
    answer(true)

    assert.equal(await first, true)
    assert.equal(built.delivered.length, 2)
    assert.equal(built.draft, null)
    assert.deepEqual(built.urls.revoked, ['blob:test/1'])
  })

  it('ignores a discard while a resend is in flight, which keeps the draft if it fails', async () => {
    let answer
    const built = build([false, new Promise((resolve) => (answer = resolve))])
    await built.sender.send(recording, 42)
    const kept = built.draft

    const resending = built.sender.resend()
    built.sender.discard()
    answer(false)

    assert.equal(await resending, false)
    assert.equal(built.draft, kept)
    assert.deepEqual(built.urls.revoked, [])
    built.sender.discard()
    assert.equal(built.draft, null)
    assert.deepEqual(built.urls.revoked, ['blob:test/1'])
  })

  it('refuses a new recording while a draft waits, so the waiting one is never overwritten', async () => {
    const built = build([false, true])
    await built.sender.send(recording, 42)
    const kept = built.draft

    assert.equal(
      await built.sender.send(new Blob(['another take'], { type: 'audio/mp4' }), 7),
      false
    )

    assert.equal(built.delivered.length, 1)
    assert.equal(built.draft, kept)
    assert.deepEqual(built.urls.revoked, [])
  })

  it('clears the draft and revokes its URL on dispose', async () => {
    const built = build([false])
    await built.sender.send(recording, 42)

    built.sender.dispose()

    assert.equal(built.draft, null)
    assert.deepEqual(built.urls.revoked, ['blob:test/1'])
  })

  it('still clears the draft and revokes its URL on dispose while a resend is in flight', async () => {
    let answer
    const built = build([false, new Promise((resolve) => (answer = resolve))])
    await built.sender.send(recording, 42)

    const resending = built.sender.resend()
    built.sender.dispose()
    answer(false)

    assert.equal(await resending, false)
    assert.equal(built.draft, null)
    assert.deepEqual(built.urls.revoked, ['blob:test/1'])
  })

  it('keeps nothing from a send that fails after the composer was disposed', async () => {
    let answer
    const built = build([new Promise((resolve) => (answer = resolve))])
    const sending = built.sender.send(recording, 42)

    built.sender.dispose()
    answer(false)

    assert.equal(await sending, false)
    assert.equal(built.draft, null)
    assert.deepEqual(built.urls.created, [])
  })
})
