import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  NOTIFICATION_DEFAULTS,
  NOTIFICATION_GROUPS,
  PRIVACY_SETTINGS
} from './notificationSettings.js'

/**
 * The grouped notification settings (#1419). The page sends every switch it knows on save, so a
 * switch missing from the page would be sent unseen, and one missing from the defaults would be
 * sent as undefined.
 *
 * Run with `npm test`.
 */

describe('the notification settings page', () => {
  it('shows every switch exactly once', () => {
    const shown = [
      ...NOTIFICATION_GROUPS.flatMap((group) =>
        group.rows.flatMap((row) => [row.email, row.push, row.bell].filter(Boolean))
      ),
      ...PRIVACY_SETTINGS.map((row) => row.setting)
    ]

    assert.deepEqual(shown.sort(), Object.keys(NOTIFICATION_DEFAULTS).sort())
  })
})
