import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { DANGER_MENU_PT } from './dangerMenu.js'

describe('DANGER_MENU_PT', () => {
  it('turns the text and the icon of a danger item red, in both themes', () => {
    const context = { item: { danger: true } }

    assert.equal(DANGER_MENU_PT.itemContent({ context }).class, '!text-red-700 dark:!text-red-400')
    assert.equal(DANGER_MENU_PT.itemIcon({ context }).class, '!text-red-700 dark:!text-red-400')
  })

  it('leaves the other items as PrimeVue draws them', () => {
    const context = { item: {} }

    assert.equal(DANGER_MENU_PT.itemContent({ context }).class, undefined)
    assert.equal(DANGER_MENU_PT.itemIcon({ context }).class, undefined)
  })
})
