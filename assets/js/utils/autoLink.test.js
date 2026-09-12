import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { autoLink } from './autoLink.js'

describe('autoLink', () => {
  it('wraps a bare URL in an anchor that opens safely', () => {
    assert.equal(
      autoLink('voir https://musicall.com/publications'),
      'voir <a href="https://musicall.com/publications" target="_blank" rel="noopener">https://musicall.com/publications</a>'
    )
  })

  it('leaves text without a URL untouched', () => {
    assert.equal(autoLink('on répète mardi'), 'on répète mardi')
  })

  it('returns an empty string for nothing at all', () => {
    assert.equal(autoLink(''), '')
    assert.equal(autoLink(null), '')
    assert.equal(autoLink(undefined), '')
  })

  it('stops at a tag boundary rather than swallowing the line break after it', () => {
    // The server turns newlines into `<br />` before this runs, so a URL ending a line sits right
    // against a tag. `[^\s<]` is what keeps the tag out of the href.
    assert.equal(
      autoLink('https://musicall.com<br />suite'),
      '<a href="https://musicall.com" target="_blank" rel="noopener">https://musicall.com</a><br />suite'
    )
  })

  it('links every URL in the message, not only the first', () => {
    const linked = autoLink('https://a.test et https://b.test')

    assert.equal(linked.match(/<a /g)?.length, 2)
  })

  it('does not touch a scheme it cannot vouch for', () => {
    assert.equal(autoLink('javascript:alert(1)'), 'javascript:alert(1)')
    assert.equal(autoLink('ftp://files.test/x'), 'ftp://files.test/x')
  })
})
