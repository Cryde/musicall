import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { checkLinkAddress } from './noteLinkAddress.js'

const LINK_ADDRESS_ERROR = "L'adresse doit commencer par http://, https:// ou mailto:"

describe('checkLinkAddress', () => {
  it('accepts http, https and mailto addresses', () => {
    assert.deepEqual(checkLinkAddress('http://musicall.com'), { href: 'http://musicall.com' })
    assert.deepEqual(checkLinkAddress('https://musicall.com/salle?id=4#plan'), {
      href: 'https://musicall.com/salle?id=4#plan'
    })
    assert.deepEqual(checkLinkAddress('mailto:son@example.com'), { href: 'mailto:son@example.com' })
  })

  it('accepts a scheme in capitals', () => {
    assert.deepEqual(checkLinkAddress('HTTPS://musicall.com'), { href: 'HTTPS://musicall.com' })
  })

  it('trims the spaces around a pasted address', () => {
    assert.deepEqual(checkLinkAddress('  https://musicall.com \n'), {
      href: 'https://musicall.com'
    })
  })

  it('refuses a blank address', () => {
    assert.deepEqual(checkLinkAddress('   '), { error: LINK_ADDRESS_ERROR })
    assert.deepEqual(checkLinkAddress(null), { error: LINK_ADDRESS_ERROR })
  })

  it('refuses a scheme on its own', () => {
    assert.deepEqual(checkLinkAddress('https://'), { error: LINK_ADDRESS_ERROR })
    assert.deepEqual(checkLinkAddress('mailto:'), { error: LINK_ADDRESS_ERROR })
  })

  it('refuses any other scheme, or none', () => {
    for (const address of [
      'javascript:alert(1)',
      'tel:+33612345678',
      'www.musicall.com',
      '/notes'
    ]) {
      assert.deepEqual(checkLinkAddress(address), { error: LINK_ADDRESS_ERROR }, address)
    }
  })

  it('refuses spaces, quotes, angle brackets and backslashes inside the address', () => {
    for (const address of [
      'https://musicall.com/une salle',
      'https://musicall.com/"x',
      "https://musicall.com/'x",
      'https://musicall.com/<x>',
      'https:\\\\musicall.com'
    ]) {
      assert.deepEqual(checkLinkAddress(address), { error: LINK_ADDRESS_ERROR }, address)
    }
  })
})
