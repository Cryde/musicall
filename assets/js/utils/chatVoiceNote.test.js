import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  formatVoiceNoteDuration,
  MICROPHONE_DENIED_MESSAGE,
  NO_MICROPHONE_MESSAGE,
  pickRecordingMimeType,
  RECORDING_FAILED_MESSAGE,
  recordingErrorMessage,
  recordingExtension
} from './chatVoiceNote.js'

/**
 * Run with `npm test`.
 */

describe('pickRecordingMimeType', () => {
  it('prefers WebM Opus, which Chrome and Firefox record', () => {
    assert.equal(
      pickRecordingMimeType(() => true),
      'audio/webm;codecs=opus'
    )
  })

  it('falls back to MP4, the only format Safari records', () => {
    assert.equal(
      pickRecordingMimeType((type) => type === 'audio/mp4'),
      'audio/mp4'
    )
  })

  it('answers null when the browser records nothing we know', () => {
    assert.equal(
      pickRecordingMimeType(() => false),
      null
    )
  })
})

describe('recordingExtension', () => {
  it('follows the container', () => {
    assert.equal(recordingExtension('audio/webm;codecs=opus'), 'webm')
    assert.equal(recordingExtension('audio/mp4'), 'm4a')
    assert.equal(recordingExtension('audio/ogg;codecs=opus'), 'ogg')
  })
})

describe('formatVoiceNoteDuration', () => {
  it('reads minutes and zero padded seconds', () => {
    assert.equal(formatVoiceNoteDuration(0), '0:00')
    assert.equal(formatVoiceNoteDuration(7.9), '0:07')
    assert.equal(formatVoiceNoteDuration(299), '4:59')
    assert.equal(formatVoiceNoteDuration(300), '5:00')
  })

  it('never goes negative', () => {
    assert.equal(formatVoiceNoteDuration(-3), '0:00')
  })
})

describe('recordingErrorMessage', () => {
  it('explains a refused permission', () => {
    assert.equal(recordingErrorMessage({ name: 'NotAllowedError' }), MICROPHONE_DENIED_MESSAGE)
  })

  it('explains a missing microphone', () => {
    assert.equal(recordingErrorMessage({ name: 'NotFoundError' }), NO_MICROPHONE_MESSAGE)
  })

  it('falls back to a generic sentence', () => {
    assert.equal(recordingErrorMessage(new Error('boom')), RECORDING_FAILED_MESSAGE)
    assert.equal(recordingErrorMessage(undefined), RECORDING_FAILED_MESSAGE)
  })
})
