import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { mediaMessageForm } from './chatMediaForm.js'

/** The fields in the order they were appended, with files reduced to their names. */
function fieldsOf(form) {
  return [...form.entries()].map(([name, value]) => [
    name,
    value instanceof File ? `file:${value.name}` : value
  ])
}

describe('mediaMessageForm', () => {
  it('sends a band chat image with its caption and attachments', () => {
    const image = new File(['x'], 'photo.jpg', { type: 'image/jpeg' })

    const form = mediaMessageForm({
      content: 'la setlist',
      attachments: ['song-1', 'task-2'],
      image
    })

    assert.deepEqual(fieldsOf(form), [
      ['content', 'la setlist'],
      ['attachments[]', 'song-1'],
      ['attachments[]', 'task-2'],
      ['image', 'file:photo.jpg']
    ])
  })

  it('names the thread of a direct conversation', () => {
    const voiceNote = new File(['x'], 'note-vocale.webm', { type: 'audio/webm' })

    const form = mediaMessageForm({ content: '', thread: '/api/message_threads/t1', voiceNote })

    assert.deepEqual(fieldsOf(form), [
      ['thread', '/api/message_threads/t1'],
      ['content', ''],
      ['voice_note', 'file:note-vocale.webm']
    ])
  })
})
