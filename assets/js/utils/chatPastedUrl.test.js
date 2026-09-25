import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { BAND_SPACE_ROUTES } from '../constants/bandSpace.js'
import { attachmentIdentifierForUrl } from './chatPastedUrl.js'

/**
 * A stand in for router.resolve(): the real one needs a browser, and what matters here is what the
 * helper does with a resolved route, not how the router parses French slugs.
 *
 * Run with `npm test`.
 */

const ORIGIN = 'https://musicall.com'
const SPACE = 'aaaa2222-3333-4444-8555-666677778888'
const TASK = '3f2504e0-4f89-41d3-9a0c-0305e82c3301'

const ROUTES = {
  '/band/': (rest, query) => {
    const [id, section] = rest.split('/')
    const name = {
      taches: BAND_SPACE_ROUTES.TASKS,
      finances: BAND_SPACE_ROUTES.FINANCE,
      agenda: BAND_SPACE_ROUTES.AGENDA,
      setlists: BAND_SPACE_ROUTES.SETLIST
    }[section]
    return { name, params: { id }, query }
  }
}

function resolve(path) {
  const url = new URL(path, ORIGIN)
  const query = Object.fromEntries(url.searchParams)
  for (const [prefix, match] of Object.entries(ROUTES)) {
    if (url.pathname.startsWith(prefix)) {
      return match(url.pathname.slice(prefix.length), query)
    }
  }
  return { name: undefined, params: {}, query }
}

const context = { origin: ORIGIN, bandSpaceId: SPACE, resolve }

describe('attachmentIdentifierForUrl', () => {
  it('turns a task link into its attachment identifier', () => {
    assert.equal(
      attachmentIdentifierForUrl(`${ORIGIN}/band/${SPACE}/taches?task=${TASK}`, context),
      `task-${TASK}`
    )
  })

  it('tells an agenda entry from a finance entry, which share the `entry` parameter', () => {
    assert.equal(
      attachmentIdentifierForUrl(`${ORIGIN}/band/${SPACE}/agenda?entry=${TASK}`, context),
      `agenda-${TASK}`
    )
    assert.equal(
      attachmentIdentifierForUrl(`${ORIGIN}/band/${SPACE}/finances?entry=${TASK}`, context),
      `finance-${TASK}`
    )
  })

  it('tells a song from a setlist on the same page', () => {
    assert.equal(
      attachmentIdentifierForUrl(`${ORIGIN}/band/${SPACE}/setlists?song=${TASK}`, context),
      `song-${TASK}`
    )
    assert.equal(
      attachmentIdentifierForUrl(`${ORIGIN}/band/${SPACE}/setlists?setlist=${TASK}`, context),
      `setlist-${TASK}`
    )
  })

  it('accepts surrounding whitespace and an upper case id', () => {
    assert.equal(
      attachmentIdentifierForUrl(
        `  ${ORIGIN}/band/${SPACE}/taches?task=${TASK.toUpperCase()}\n`,
        context
      ),
      `task-${TASK}`
    )
  })

  it('leaves a link to another band as text', () => {
    const other = 'bbbb2222-3333-4444-8555-666677778888'
    assert.equal(
      attachmentIdentifierForUrl(`${ORIGIN}/band/${other}/taches?task=${TASK}`, context),
      null
    )
  })

  it('leaves another site alone, even with the same path', () => {
    assert.equal(
      attachmentIdentifierForUrl(`https://evil.example/band/${SPACE}/taches?task=${TASK}`, context),
      null
    )
  })

  it('leaves a module page with no object in it as text', () => {
    assert.equal(attachmentIdentifierForUrl(`${ORIGIN}/band/${SPACE}/taches`, context), null)
    assert.equal(
      attachmentIdentifierForUrl(`${ORIGIN}/band/${SPACE}/taches?task=pas-un-uuid`, context),
      null
    )
  })

  it('leaves a sentence containing a link as text', () => {
    assert.equal(
      attachmentIdentifierForUrl(`regarde ${ORIGIN}/band/${SPACE}/taches?task=${TASK}`, context),
      null
    )
    assert.equal(attachmentIdentifierForUrl('pas une url', context), null)
    assert.equal(attachmentIdentifierForUrl('', context), null)
  })
})
