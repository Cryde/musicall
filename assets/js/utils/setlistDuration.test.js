import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  DURATION_FORMAT_MESSAGE,
  DURATION_RANGE_MESSAGE,
  formatDuration,
  MAX_DURATION_SECONDS,
  parseDurationInput
} from './setlistDuration.js'

/**
 * These pin the two halves of the contract that lets a member type "3:47" into a field the API
 * still stores as 227 seconds.
 *
 * The parsing side matters most on the seeding path: a 40 song repertoire is entered in one sitting,
 * so a slip has to be refused with a message rather than silently reinterpreted. 3:75 is the case
 * worth reading twice, it is refused, because reading it as 4:15 would mean the field gave back
 * something other than what was typed.
 *
 * The formatting side matters because the same number used to be rendered three different ways for
 * the same setlist. There is one format now, and the round trip tests at the bottom are what keep it
 * honest: whatever this displays has to parse back to the number it came from.
 *
 * Run with `npm test`.
 */

describe('formatDuration', () => {
  it('writes a duration under a minute with an explicit zero minute', () => {
    assert.equal(formatDuration(47), '0:47')
    assert.equal(formatDuration(7), '0:07')
  })

  it('writes minutes and seconds with the seconds always on two digits', () => {
    assert.equal(formatDuration(227), '3:47')
    assert.equal(formatDuration(187), '3:07')
    assert.equal(formatDuration(600), '10:00')
  })

  it('rolls over to hours, which the PDF header used to refuse to do', () => {
    // 5850s is the "97 min 30 s" the old header printed for a full set.
    assert.equal(formatDuration(5850), '1:37:30')
    assert.equal(formatDuration(3600), '1:00:00')
    assert.equal(formatDuration(3661), '1:01:01')
    assert.equal(formatDuration(MAX_DURATION_SECONDS), '24:00:00')
  })

  it('writes a real zero as a zero, because an empty setlist still has a total', () => {
    assert.equal(formatDuration(0), '0:00')
  })

  it('writes an absent duration as nothing, leaving the placeholder to the caller', () => {
    assert.equal(formatDuration(null), '')
    assert.equal(formatDuration(undefined), '')
    assert.equal(formatDuration(''), '')
  })

  it('writes anything unusable as nothing rather than NaN', () => {
    assert.equal(formatDuration('abc'), '')
    assert.equal(formatDuration(Number.NaN), '')
    assert.equal(formatDuration(-1), '')
  })
})

describe('parseDurationInput', () => {
  it('reads mm:ss as minutes and seconds', () => {
    assert.deepEqual(parseDurationInput('3:47'), { seconds: 227, error: null })
    assert.deepEqual(parseDurationInput('03:47'), { seconds: 227, error: null })
    assert.deepEqual(parseDurationInput('0:59'), { seconds: 59, error: null })
  })

  it('reads a single digit of seconds, since 3:7 says only one thing', () => {
    assert.deepEqual(parseDurationInput('3:7'), { seconds: 187, error: null })
  })

  it('leaves the minutes uncapped so a long break can be written 90:00', () => {
    assert.deepEqual(parseDurationInput('90:00'), { seconds: 5400, error: null })
    assert.deepEqual(parseDurationInput('97:30'), { seconds: 5850, error: null })
  })

  it('still reads a bare number as a number of seconds', () => {
    assert.deepEqual(parseDurationInput('227'), { seconds: 227, error: null })
    assert.deepEqual(parseDurationInput(227), { seconds: 227, error: null })
    assert.deepEqual(parseDurationInput(String(MAX_DURATION_SECONDS)), {
      seconds: MAX_DURATION_SECONDS,
      error: null
    })
  })

  it('ignores whitespace around what was typed', () => {
    assert.deepEqual(parseDurationInput('  3:47  '), { seconds: 227, error: null })
  })

  it('reads an empty field as no duration, which is how one is cleared', () => {
    assert.deepEqual(parseDurationInput(''), { seconds: null, error: null })
    assert.deepEqual(parseDurationInput('   '), { seconds: null, error: null })
    assert.deepEqual(parseDurationInput(null), { seconds: null, error: null })
    assert.deepEqual(parseDurationInput(undefined), { seconds: null, error: null })
  })

  it('refuses seconds over 59 instead of quietly carrying them into the minutes', () => {
    // 3:75 is a slip, not a time. Reading it as 4:15 would give back something else than typed.
    assert.deepEqual(parseDurationInput('3:75'), { seconds: null, error: DURATION_FORMAT_MESSAGE })
    assert.deepEqual(parseDurationInput('3:60'), { seconds: null, error: DURATION_FORMAT_MESSAGE })
  })

  it('refuses a half written clock', () => {
    assert.deepEqual(parseDurationInput('3:'), { seconds: null, error: DURATION_FORMAT_MESSAGE })
    assert.deepEqual(parseDurationInput(':47'), { seconds: null, error: DURATION_FORMAT_MESSAGE })
    assert.deepEqual(parseDurationInput(':'), { seconds: null, error: DURATION_FORMAT_MESSAGE })
  })

  it('reads a full clock, so a total read off the header can be typed back', () => {
    assert.deepEqual(parseDurationInput('1:37:30'), { seconds: 5850, error: null })
    assert.deepEqual(parseDurationInput('1:00:00'), { seconds: 3600, error: null })
    assert.deepEqual(parseDurationInput('24:00:00'), {
      seconds: MAX_DURATION_SECONDS,
      error: null
    })
  })

  it('refuses a third field that is not written as a clock, so 3:7:9 is never guessed at', () => {
    // Both trailing fields have to be two digits. 3:7:9 could be 3h07m09s or three of anything,
    // and a duration field is not the place to pick.
    assert.deepEqual(parseDurationInput('3:7:9'), { seconds: null, error: DURATION_FORMAT_MESSAGE })
    assert.deepEqual(parseDurationInput('1:7:30'), {
      seconds: null,
      error: DURATION_FORMAT_MESSAGE
    })
    assert.deepEqual(parseDurationInput('1:60:00'), {
      seconds: null,
      error: DURATION_FORMAT_MESSAGE
    })
    assert.deepEqual(parseDurationInput('1:30:75'), {
      seconds: null,
      error: DURATION_FORMAT_MESSAGE
    })
    assert.deepEqual(parseDurationInput('1:2:3:4'), {
      seconds: null,
      error: DURATION_FORMAT_MESSAGE
    })
  })

  it('refuses anything that is not a duration', () => {
    assert.deepEqual(parseDurationInput('abc'), { seconds: null, error: DURATION_FORMAT_MESSAGE })
    assert.deepEqual(parseDurationInput('3,47'), { seconds: null, error: DURATION_FORMAT_MESSAGE })
    assert.deepEqual(parseDurationInput('3.47'), { seconds: null, error: DURATION_FORMAT_MESSAGE })
    assert.deepEqual(parseDurationInput('-5'), { seconds: null, error: DURATION_FORMAT_MESSAGE })
    assert.deepEqual(parseDurationInput('3m47'), { seconds: null, error: DURATION_FORMAT_MESSAGE })
  })

  it('refuses a zero, which the API would refuse too', () => {
    // Assert\Range(min: 1) on both SongCreate and SetlistItemCreate. A duration of zero is either a
    // mistake or a clear, and a clear is an empty field.
    assert.deepEqual(parseDurationInput('0'), { seconds: null, error: DURATION_RANGE_MESSAGE })
    assert.deepEqual(parseDurationInput('0:00'), { seconds: null, error: DURATION_RANGE_MESSAGE })
  })

  it('refuses more than the API ceiling of 24 hours', () => {
    assert.deepEqual(parseDurationInput(String(MAX_DURATION_SECONDS + 1)), {
      seconds: null,
      error: DURATION_RANGE_MESSAGE
    })
    assert.deepEqual(parseDurationInput('1441:00'), {
      seconds: null,
      error: DURATION_RANGE_MESSAGE
    })
  })
})

describe('the field round trip', () => {
  // A field is seeded with formatDuration and read back with parseDurationInput, so opening an item
  // and saving it untouched has to store the very same number of seconds.
  it('gives back the seconds it was seeded with, hours included', () => {
    const values = [1, 47, 187, 227, 258, 600, 3599, 3600, 3661, 5850, MAX_DURATION_SECONDS]

    for (const seconds of values) {
      const displayed = formatDuration(seconds)
      assert.deepEqual(
        parseDurationInput(displayed),
        { seconds, error: null },
        `${seconds}s displayed as "${displayed}" must parse back to ${seconds}`
      )
    }
  })

  it('accepts the same duration written either way', () => {
    // 61 minutes and 1 hour 1 minute are the same thing, and the field takes both.
    assert.deepEqual(parseDurationInput('61:00'), { seconds: 3660, error: null })
    assert.deepEqual(parseDurationInput('1:01:00'), { seconds: 3660, error: null })
    assert.equal(formatDuration(3660), '1:01:00')
  })
})
