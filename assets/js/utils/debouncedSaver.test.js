import assert from 'node:assert/strict'
import { afterEach, beforeEach, describe, it, mock } from 'node:test'
import { createDebouncedSaver } from './debouncedSaver.js'

/**
 * These pin the note autosave loop, and above all its end. A note body is written wholesale by a
 * timer, so a save the server refuses because someone else has written since must not be retried
 * every two seconds, and must not be sent one last time by the flush on unmount. That is the
 * behaviour `stop` carries, and it is the difference between a refused save and a lost document.
 *
 * Run with `npm test`. Node's own runner and its fake timers, so this costs no dependency. The
 * TipTap wiring around the saver still has no coverage, because that would need a browser.
 */

const DELAY_MS = 2000

/**
 * Lets the promise queue drain. The saver awaits each save before starting the next, so a timer tick
 * alone does not tell the whole story: the state that decides whether the next one may start settles
 * a microtask later. Fake timers do not advance microtasks, hence the explicit turns.
 */
async function settle() {
  for (let i = 0; i < 3; i++) {
    await Promise.resolve()
  }
}

describe('createDebouncedSaver', () => {
  beforeEach(() => {
    mock.timers.enable({ apis: ['setTimeout'] })
  })

  afterEach(() => {
    mock.timers.reset()
  })

  describe('while saving normally', () => {
    it('saves once the delay has passed, and not before', () => {
      const saves = []
      const saver = createDebouncedSaver({ delayMs: DELAY_MS, onSave: (c) => saves.push(c) })

      saver.schedule('première frappe')
      mock.timers.tick(DELAY_MS - 1)
      assert.deepEqual(saves, [])

      mock.timers.tick(1)
      assert.deepEqual(saves, ['première frappe'])
    })

    it('keeps only the latest content when edits arrive inside the delay', () => {
      const saves = []
      const saver = createDebouncedSaver({ delayMs: DELAY_MS, onSave: (c) => saves.push(c) })

      saver.schedule('un')
      mock.timers.tick(DELAY_MS - 1)
      saver.schedule('deux')
      mock.timers.tick(DELAY_MS)

      assert.deepEqual(saves, ['deux'])
    })

    it('saves each edit separately once they are further apart than the delay', async () => {
      const saves = []
      const saver = createDebouncedSaver({ delayMs: DELAY_MS, onSave: (c) => saves.push(c) })

      saver.schedule('un')
      mock.timers.tick(DELAY_MS)
      await settle()

      saver.schedule('deux')
      mock.timers.tick(DELAY_MS)
      await settle()

      assert.deepEqual(saves, ['un', 'deux'])
    })
  })

  describe('overlapping saves, which a slow round trip produces', () => {
    it('waits for the save in flight and then writes only the latest content', async () => {
      const saves = []
      let releaseFirstSave = null
      const saver = createDebouncedSaver({
        delayMs: DELAY_MS,
        onSave: (content) => {
          saves.push(content)
          if (saves.length > 1) return undefined

          return new Promise((resolve) => {
            releaseFirstSave = resolve
          })
        }
      })

      saver.schedule('première')
      mock.timers.tick(DELAY_MS)
      await settle()
      assert.deepEqual(saves, ['première'], 'the first save is in flight')

      // The writer keeps typing while it hangs. Neither edit may open a second concurrent save: both
      // would name the revision they began from, and the later one would come back refused as though
      // somebody else had written to the note.
      saver.schedule('première et deuxième')
      mock.timers.tick(DELAY_MS)
      await settle()
      saver.schedule('première, deuxième et troisième')
      mock.timers.tick(DELAY_MS)
      await settle()
      assert.deepEqual(saves, ['première'], 'nothing else goes out while one is in flight')

      releaseFirstSave()
      await settle()

      assert.deepEqual(
        saves,
        ['première', 'première, deuxième et troisième'],
        'only the newest content follows, the superseded edit is dropped'
      )
    })

    it('drops a queued save when the loop is stopped mid flight', async () => {
      const saves = []
      let releaseFirstSave = null
      const saver = createDebouncedSaver({
        delayMs: DELAY_MS,
        onSave: (content) => {
          saves.push(content)

          return new Promise((resolve) => {
            releaseFirstSave = resolve
          })
        }
      })

      saver.schedule('première')
      mock.timers.tick(DELAY_MS)
      await settle()

      saver.schedule('deuxième')
      mock.timers.tick(DELAY_MS)
      await settle()

      saver.stop()
      releaseFirstSave()
      await settle()

      assert.deepEqual(saves, ['première'], 'the queued document is never sent after a stop')
    })

    /**
     * The contract the note editor relies on: it stops from inside the save callback, on the
     * refused answer, rather than from a watcher on the save status where the stop would land a
     * scheduling hop later and the refused document could go out once more.
     *
     * Stated rather than defended: the saver guards this twice, since `stop` clears the queue as
     * well as raising the flag, so no single change to it makes this fail on its own. What it fixes
     * in place is that a stop made from inside an awaited save is in time, which is what allows the
     * editor to have no ordering assumption left to get wrong.
     */
    it('is stopped in time by a save that stops the loop itself', async () => {
      const saves = []
      let releaseFirstSave = null
      const saver = createDebouncedSaver({
        delayMs: DELAY_MS,
        onSave: async (content) => {
          saves.push(content)
          await new Promise((resolve) => {
            releaseFirstSave = resolve
          })
          saver.stop()
        }
      })

      saver.schedule('première')
      mock.timers.tick(DELAY_MS)
      await settle()

      // The writer keeps typing while the save that is about to be refused is still in flight.
      saver.schedule('deuxième')
      mock.timers.tick(DELAY_MS)
      await settle()

      releaseFirstSave()
      await settle()

      assert.deepEqual(saves, ['première'], 'the queued document is never sent after the refusal')
    })
  })

  describe('flush, which is what runs when the editor is unmounted', () => {
    it('writes what is still waiting, immediately', () => {
      const saves = []
      const saver = createDebouncedSaver({ delayMs: DELAY_MS, onSave: (c) => saves.push(c) })

      saver.schedule('dernière phrase')
      saver.flush()

      assert.deepEqual(saves, ['dernière phrase'])
    })

    it('does not save the same content twice when the timer would still have fired', () => {
      const saves = []
      const saver = createDebouncedSaver({ delayMs: DELAY_MS, onSave: (c) => saves.push(c) })

      saver.schedule('dernière phrase')
      saver.flush()
      mock.timers.tick(DELAY_MS)

      assert.deepEqual(saves, ['dernière phrase'])
    })

    it('saves nothing when nothing is waiting', () => {
      const saves = []
      const saver = createDebouncedSaver({ delayMs: DELAY_MS, onSave: (c) => saves.push(c) })

      saver.flush()

      assert.deepEqual(saves, [])
    })
  })

  describe('cancel, which drops what is waiting without ending the loop', () => {
    it('stops a pending save from firing', () => {
      const saves = []
      const saver = createDebouncedSaver({ delayMs: DELAY_MS, onSave: (c) => saves.push(c) })

      saver.schedule('abandonné')
      saver.cancel()
      mock.timers.tick(DELAY_MS)

      assert.deepEqual(saves, [])
    })

    it('leaves the saver usable afterwards', () => {
      const saves = []
      const saver = createDebouncedSaver({ delayMs: DELAY_MS, onSave: (c) => saves.push(c) })

      saver.schedule('abandonné')
      saver.cancel()
      saver.schedule('repris')
      mock.timers.tick(DELAY_MS)

      assert.deepEqual(saves, ['repris'])
    })
  })

  describe('stop, which is what a refused save triggers', () => {
    it('never fires the save that was already pending', () => {
      const saves = []
      const saver = createDebouncedSaver({ delayMs: DELAY_MS, onSave: (c) => saves.push(c) })

      saver.schedule('refusé')
      saver.stop()
      mock.timers.tick(DELAY_MS * 10)

      assert.deepEqual(saves, [])
    })

    // The hammering the conflict banner exists to prevent: every keystroke afterwards would
    // otherwise re-send a document the server has already refused.
    it('arms nothing for any edit made afterwards', () => {
      const saves = []
      const saver = createDebouncedSaver({ delayMs: DELAY_MS, onSave: (c) => saves.push(c) })

      saver.stop()
      saver.schedule('encore une frappe')
      saver.schedule('et une autre')
      mock.timers.tick(DELAY_MS * 10)

      assert.deepEqual(saves, [])
    })

    // Unmounting flushes, so without this the refused document would be written on the way out,
    // which is exactly the overwrite the whole guard is there to prevent.
    it('writes nothing when the editor is unmounted afterwards', () => {
      const saves = []
      const saver = createDebouncedSaver({ delayMs: DELAY_MS, onSave: (c) => saves.push(c) })

      saver.schedule('refusé')
      saver.stop()
      saver.flush()

      assert.deepEqual(saves, [])
    })

    it('is permanent, so a cancel does not put the loop back', () => {
      const saves = []
      const saver = createDebouncedSaver({ delayMs: DELAY_MS, onSave: (c) => saves.push(c) })

      saver.stop()
      saver.cancel()
      saver.schedule('encore une frappe')
      mock.timers.tick(DELAY_MS)

      assert.deepEqual(saves, [])
    })
  })
})
