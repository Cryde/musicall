import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { useMentionParser } from './useMentionParser.js'

const { findMentionQuery, getSuggestions } = useMentionParser()

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
