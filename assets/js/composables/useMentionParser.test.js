import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { useMentionParser } from './useMentionParser.js'

const { findMentionQuery, getSuggestions, parseToParts } = useMentionParser()

const MEMBERS = [
  { user_id: 'tous', username: 'tous' },
  { user_id: '550e8400-e29b-41d4-a716-446655440000', username: 'bassiste' },
  { user_id: '6ba7b810-9dad-11d1-80b4-00c04fd430c8', username: 'batteur' }
]

describe('findMentionQuery', () => {
  it('opens on a bare @, with an empty query', () => {
    // The whole point of the rule: `@tous` is undiscoverable if you have to guess that typing `@t`
    // is what reveals it.
    assert.equal(findMentionQuery('@'), '')
    assert.equal(findMentionQuery('salut @'), '')
  })

  it('returns what has been typed after the @', () => {
    assert.equal(findMentionQuery('salut @bas'), 'bas')
  })

  it('ignores an @ in the middle of a word', () => {
    // An email address must not open the roster, which only became possible once a bare @ was enough.
    assert.equal(findMentionQuery('jeremy@'), null)
    assert.equal(findMentionQuery('écris à jeremy@musicall.com'), null)
  })

  it('closes once a space follows the @', () => {
    assert.equal(findMentionQuery('@bassiste a dit'), null)
  })

  it('ignores a caret sitting inside an already inserted mention', () => {
    assert.equal(findMentionQuery('salut @[550e8400-e29b-41d4-a716-446655440000'), null)
  })

  it('is null when there is no @ at all', () => {
    assert.equal(findMentionQuery('on répète mardi'), null)
  })

  it('reads the last @ rather than the first', () => {
    assert.equal(findMentionQuery('@bassiste salut @bat'), 'bat')
  })

  it('opens after a newline, which is a word start too', () => {
    assert.equal(findMentionQuery('première ligne\n@'), '')
  })
})

describe('getSuggestions', () => {
  it('offers everybody for an empty query', () => {
    assert.deepEqual(getSuggestions('', MEMBERS), MEMBERS)
  })

  it('filters on the start of the username', () => {
    assert.deepEqual(
      getSuggestions('bas', MEMBERS).map((m) => m.username),
      ['bassiste']
    )
  })

  it('ignores case', () => {
    assert.deepEqual(
      getSuggestions('BAT', MEMBERS).map((m) => m.username),
      ['batteur']
    )
  })

  it('matches nothing when nobody starts that way', () => {
    assert.deepEqual(getSuggestions('zz', MEMBERS), [])
  })

  it('offers tous like anybody else', () => {
    assert.deepEqual(
      getSuggestions('to', MEMBERS).map((m) => m.username),
      ['tous']
    )
  })
})

describe('parseToParts', () => {
  const [BASSISTE, BATTEUR] = [MEMBERS[1], MEMBERS[2]]

  it('reads nothing from nothing', () => {
    assert.deepEqual(parseToParts('', MEMBERS), [])
  })

  it('carries the id as well as the name, because the editor needs both', () => {
    // The name is what a reader sees, the id is what is stored. A chip is built from the pair.
    assert.deepEqual(parseToParts(`salut @[${BASSISTE.user_id}] !`, MEMBERS), [
      { type: 'text', value: 'salut ' },
      { type: 'mention', userId: BASSISTE.user_id, username: 'bassiste' },
      { type: 'text', value: ' !' }
    ])
  })

  it('keeps the id of somebody the roster cannot name', () => {
    // A member who left. An edit must not drop the mention just because the label is gone.
    assert.deepEqual(parseToParts(`@[${BATTEUR.user_id}]`, []), [
      { type: 'mention', userId: BATTEUR.user_id, username: 'inconnu' }
    ])
  })

  it('reads two mentions in a row as two mentions', () => {
    assert.deepEqual(parseToParts(`@[${BASSISTE.user_id}]@[${BATTEUR.user_id}]`, MEMBERS), [
      { type: 'mention', userId: BASSISTE.user_id, username: 'bassiste' },
      { type: 'mention', userId: BATTEUR.user_id, username: 'batteur' }
    ])
  })

  it('leaves the everyone sentinel as plain text', () => {
    // Pinned on purpose. The chat composer can write `@[tous]` but nothing reads it back yet, and
    // widening the pattern would turn a literal `@[tous]` in a task comment into a mention the
    // server never agreed to. Chat editing (#966) is where this has to be decided.
    assert.deepEqual(parseToParts('@[tous] répète annulée', MEMBERS), [
      { type: 'text', value: '@[tous] répète annulée' }
    ])
  })

  it('names the same member whatever case the uuid is written in', () => {
    // The server lowercases before comparing, so a mention it would notify has to be one this can
    // name. The id is kept verbatim, which is what makes the round trip through an edit box exact.
    const upper = BASSISTE.user_id.toUpperCase()
    assert.deepEqual(parseToParts(`@[${upper}]`, MEMBERS), [
      { type: 'mention', userId: upper, username: 'bassiste' }
    ])
  })
})
