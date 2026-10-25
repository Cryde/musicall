/**
 * The multipart body of a chat message carrying an image or a voice note: the band chat (#973, #974)
 * and a direct conversation (#1247), which names its `thread` in the body.
 *
 * `attachments[]` rather than one JSON encoded field, so the array reaches the server as an array
 * whatever the multipart decoder does with a string. The browser sets the multipart boundary itself.
 *
 * @param {object} message
 * @param {string} message.content
 * @param {string[]} [message.attachments]
 * @param {string|null} [message.thread] the thread IRI, for a direct conversation
 * @param {File|null} [message.image]
 * @param {File|null} [message.voiceNote]
 * @returns {FormData}
 */
export function mediaMessageForm({
  content,
  attachments = [],
  thread = null,
  image = null,
  voiceNote = null
}) {
  const form = new FormData()
  if (thread) form.append('thread', thread)
  form.append('content', content)
  for (const attachment of attachments) {
    form.append('attachments[]', attachment)
  }
  if (image) form.append('image', image)
  if (voiceNote) form.append('voice_note', voiceNote)

  return form
}
