import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  RECONNECT_MAX_BASE_DELAY_MS,
  RECONNECT_MAX_DELAY_MS,
  reconnectDelay
} from './realtimeBackoff.js'

/**
 * The reconnect delay for the notification stream. `random` is injected so these assert the spread
 * rather than sample it.
 *
 * Run with `npm test`.
 */

describe('reconnectDelay', () => {
  it('waits about a second on the first retry', () => {
    // Mid-jitter, so the raw exponential.
    assert.equal(
      reconnectDelay(0, () => 0.5),
      1000
    )
  })

  it('grows exponentially', () => {
    assert.equal(
      reconnectDelay(1, () => 0.5),
      2000
    )
    assert.equal(
      reconnectDelay(2, () => 0.5),
      4000
    )
    assert.equal(
      reconnectDelay(3, () => 0.5),
      8000
    )
  })

  it('stops growing at the cap', () => {
    assert.equal(
      reconnectDelay(20, () => 0.5),
      RECONNECT_MAX_BASE_DELAY_MS
    )
    assert.equal(
      reconnectDelay(9999, () => 0.5),
      RECONNECT_MAX_BASE_DELAY_MS
    )
  })

  it('never exceeds the advertised ceiling, jitter included', () => {
    // The cap applies before the spread, so the exported maximum has to account for it. Asserting
    // only the mid-jitter point would have proved the exponent stops growing and nothing more.
    for (const roll of [0, 0.5, 0.999999]) {
      for (const attempt of [0, 3, 20, 9999]) {
        assert.ok(
          reconnectDelay(attempt, () => roll) <= RECONNECT_MAX_DELAY_MS,
          `attempt ${attempt} roll ${roll} exceeded ${RECONNECT_MAX_DELAY_MS}`
        )
      }
    }
  })

  it('spreads clients either side of the delay, which is the whole point', () => {
    // Two browsers dropped by the same deploy restart, at the same attempt, come back apart.
    assert.equal(
      reconnectDelay(2, () => 0),
      2000
    )
    assert.equal(
      reconnectDelay(2, () => 1),
      6000
    )
  })

  it('never returns a negative or fractional delay', () => {
    for (const roll of [0, 0.25, 0.5, 0.75, 1]) {
      for (const attempt of [0, 1, 5, 50]) {
        const delay = reconnectDelay(attempt, () => roll)
        assert.ok(delay >= 0, `attempt ${attempt} roll ${roll} gave ${delay}`)
        assert.equal(delay, Math.round(delay))
      }
    }
  })

  it('treats a nonsense attempt as the first one', () => {
    // setTimeout(fn, NaN) fires immediately, so a bad counter must not become a hot loop.
    assert.equal(
      reconnectDelay(Number.NaN, () => 0.5),
      1000
    )
    assert.equal(
      reconnectDelay(-3, () => 0.5),
      1000
    )
  })
})
