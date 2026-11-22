import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { workByline, workForm, workLinkDomain, workPayload } from './serviceWorks.js'

describe('workForm', () => {
  it('starts empty for a new work', () => {
    assert.deepEqual(workForm(), { title: '', credit: '', year: null, link_url: '', media_url: '' })
  })

  it('reads a work back, its embed by its address', () => {
    assert.deepEqual(
      workForm({
        title: 'Satellites',
        credit: 'Mona Lisa Overdrive, EP',
        year: 2024,
        link_url: 'https://example.com',
        media: { url: 'https://youtu.be/x' }
      }),
      {
        title: 'Satellites',
        credit: 'Mona Lisa Overdrive, EP',
        year: 2024,
        link_url: 'https://example.com',
        media_url: 'https://youtu.be/x'
      }
    )
  })
})

describe('workPayload', () => {
  it('trims, and sends a blank as null so a PATCH empties it', () => {
    assert.deepEqual(
      workPayload({
        title: '  Satellites ',
        credit: '  ',
        year: null,
        link_url: '',
        media_url: ' https://youtu.be/x '
      }),
      {
        title: 'Satellites',
        credit: null,
        year: null,
        link_url: null,
        media_url: 'https://youtu.be/x'
      }
    )
  })

  it('keeps a year only when it is a whole number', () => {
    assert.equal(workPayload({ ...workForm(), year: 2024 }).year, 2024)
    assert.equal(workPayload({ ...workForm(), year: undefined }).year, null)
  })
})

describe('workByline', () => {
  it('joins the credit and the year', () => {
    assert.equal(
      workByline({ credit: 'Mona Lisa Overdrive, EP', year: 2024 }),
      'Mona Lisa Overdrive, EP · 2024'
    )
  })

  it('shows whichever half there is', () => {
    assert.equal(workByline({ credit: null, year: 2024 }), '2024')
    assert.equal(workByline({ credit: 'Satellites', year: null }), 'Satellites')
    assert.equal(workByline({ credit: null, year: null }), '')
  })
})

describe('workLinkDomain', () => {
  it('names the site without its www', () => {
    assert.equal(workLinkDomain('https://www.bandcamp.com/album/satellites'), 'bandcamp.com')
    assert.equal(workLinkDomain('http://monalisa.be'), 'monalisa.be')
  })

  it('has nothing for an address that is not a web one', () => {
    assert.equal(workLinkDomain('javascript:alert(1)'), null)
    assert.equal(workLinkDomain('not a link'), null)
    assert.equal(workLinkDomain(null), null)
  })
})
