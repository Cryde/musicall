import assert from 'node:assert/strict'
import { afterEach, beforeEach, describe, it, mock } from 'node:test'
import { useVoiceRecorder } from './useVoiceRecorder.js'

/**
 * The recorder's state machine against a fake MediaRecorder that behaves like the real one where it
 * matters: `state` turns inactive as soon as stop() is called, `onstop` fires later.
 *
 * Run with `npm test`.
 */

let now = 0
let recorders = []
let stoppedTracks = 0

class FakeMediaRecorder {
  static isTypeSupported(type) {
    return type === 'audio/webm;codecs=opus'
  }

  constructor(stream, { mimeType }) {
    this.stream = stream
    this.mimeType = mimeType
    this.state = 'inactive'
    recorders.push(this)
  }

  start() {
    this.state = 'recording'
  }

  stop() {
    this.state = 'inactive'
    setTimeout(() => {
      this.ondataavailable?.({ data: new Blob(['sound'], { type: this.mimeType }) })
      this.onstop?.()
    }, 0)
  }
}

function fakeStream() {
  return { getTracks: () => [{ stop: () => stoppedTracks++ }] }
}

beforeEach(() => {
  now = 0
  recorders = []
  stoppedTracks = 0
  mock.method(Date, 'now', () => now)
  globalThis.window = { MediaRecorder: FakeMediaRecorder }
  Object.defineProperty(globalThis, 'navigator', {
    configurable: true,
    value: { mediaDevices: { getUserMedia: async () => fakeStream() } }
  })
})

afterEach(() => {
  mock.restoreAll()
})

describe('useVoiceRecorder', () => {
  it('records, then hands the recording back and releases the microphone', async () => {
    const recorder = useVoiceRecorder({ onLimitReached: () => {} })
    await recorder.start()
    assert.equal(recorder.isRecording.value, true)

    now = 3000
    const recording = await recorder.stop()

    assert.equal(recording.type, 'audio/webm;codecs=opus')
    assert.equal(recorder.isRecording.value, false)
    assert.equal(stoppedTracks, 1)
  })

  it('drops a recording shorter than a second', async () => {
    const recorder = useVoiceRecorder({ onLimitReached: () => {} })
    await recorder.start()

    now = 400
    assert.equal(await recorder.stop(), null)
    assert.equal(stoppedTracks, 1)
  })

  it('ignores a second start while recording', async () => {
    const recorder = useVoiceRecorder({ onLimitReached: () => {} })
    await recorder.start()
    await recorder.start()

    assert.equal(recorders.length, 1)
    recorder.cancel()
    // Let the cancelled recorder settle here, not during the next test.
    await new Promise((resolve) => setTimeout(resolve, 5))
  })

  it('answers a stop that is still settling with null when cancelled, rather than never', async () => {
    const recorder = useVoiceRecorder({ onLimitReached: () => {} })
    await recorder.start()
    now = 3000

    const stopping = recorder.stop()
    recorder.cancel()

    assert.equal(await stopping, null)
    await new Promise((resolve) => setTimeout(resolve, 5))
    assert.equal(recorder.isRecording.value, false)
    assert.equal(stoppedTracks, 1)
  })

  it('cancels a running recording and releases the microphone', async () => {
    const recorder = useVoiceRecorder({ onLimitReached: () => {} })
    await recorder.start()

    recorder.cancel()
    await new Promise((resolve) => setTimeout(resolve, 5))

    assert.equal(recorder.isRecording.value, false)
    assert.equal(stoppedTracks, 1)
  })

  it('explains a refused microphone and records nothing', async () => {
    navigator.mediaDevices.getUserMedia = async () => {
      throw Object.assign(new Error('denied'), { name: 'NotAllowedError' })
    }
    const recorder = useVoiceRecorder({ onLimitReached: () => {} })

    await recorder.start()

    assert.equal(recorder.isRecording.value, false)
    assert.match(recorder.error.value, /L'accès au micro a été refusé/)
  })
})
