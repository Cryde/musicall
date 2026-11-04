import assert from 'node:assert/strict'
import { after, afterEach, before, describe, it } from 'node:test'
import en from '../i18n/en/index.js'
import { i18n } from '../i18n/index.js'
import { conversationTimeLabel } from './conversationTime.js'

/**
 * When a conversation last spoke, as its row in the inbox, the envelope and the band home say it.
 * Run with `npm test`.
 */

// West of UTC is where a calendar day read in UTC would be the wrong one.
for (const timeZone of ['Europe/Paris', 'America/Martinique']) {
  describe(`conversationTimeLabel in ${timeZone}`, () => {
    const original = process.env.TZ
    before(() => {
      process.env.TZ = timeZone
    })
    after(() => {
      process.env.TZ = original
    })

    it('shows the time today, « Hier », then the day', () => {
      const morning = new Date(2026, 10, 4, 9, 30)

      assert.equal(conversationTimeLabel(new Date(2026, 10, 4, 9, 7), morning), '09:07')
      assert.equal(conversationTimeLabel(new Date(2026, 10, 3, 23, 50), morning), 'Hier')
      assert.equal(conversationTimeLabel(new Date(2026, 10, 1, 18, 0), morning), '1 nov.')
    })

    it('reads the ISO string the API sends', () => {
      const morning = new Date(2026, 10, 4, 9, 30)
      const sevenPast = new Date(2026, 10, 4, 9, 7)

      assert.equal(conversationTimeLabel(sevenPast.toISOString(), morning), '09:07')
    })
  })
}

describe('conversationTimeLabel without a date', () => {
  it('says nothing for a conversation with no message yet, or a value it cannot read', () => {
    assert.equal(conversationTimeLabel(null), '')
    assert.equal(conversationTimeLabel(undefined), '')
    assert.equal(conversationTimeLabel('not a date'), '')
  })
})

describe('conversationTimeLabel in English', () => {
  // English is loaded on demand in the app (locale.js), so the test loads it itself.
  before(() => {
    i18n.global.setLocaleMessage('en', en)
  })

  afterEach(() => {
    i18n.global.locale.value = 'fr'
  })

  it('says yesterday in English', () => {
    i18n.global.locale.value = 'en'

    assert.equal(
      conversationTimeLabel(new Date(2026, 10, 3, 20, 0), new Date(2026, 10, 4, 9, 30)),
      'Yesterday'
    )
  })
})
