import assert from 'node:assert/strict'
import { afterEach, describe, it } from 'node:test'
import { i18n } from '../i18n/index.js'
import { formatBytes } from './formatBytes.js'

describe('formatBytes', () => {
  afterEach(() => {
    i18n.global.locale.value = 'fr'
  })

  it('writes French units, one decimal up to megabytes and two for gigabytes', () => {
    assert.equal(formatBytes(900), '900 o')
    assert.equal(formatBytes(1280), '1.3 Ko')
    assert.equal(formatBytes(4_404_019), '4.2 Mo')
    assert.equal(formatBytes(5_368_709_120), '5.00 Go')
  })

  it('writes English units', () => {
    i18n.global.locale.value = 'en'

    assert.equal(formatBytes(900), '900 B')
    assert.equal(formatBytes(1280), '1.3 KB')
    assert.equal(formatBytes(4_404_019), '4.2 MB')
    assert.equal(formatBytes(5_368_709_120), '5.00 GB')
  })

  it('takes the number of decimals asked for', () => {
    assert.equal(formatBytes(327_680, { decimals: 0 }), '320 Ko')
    assert.equal(formatBytes(5_368_709_120, { decimals: 0 }), '5 Go')
  })

  it('stands in for a size that is missing', () => {
    assert.equal(formatBytes(null), '—')
    assert.equal(formatBytes(undefined), '—')
    i18n.global.locale.value = 'en'
    assert.equal(formatBytes(null), '-')
  })
})
