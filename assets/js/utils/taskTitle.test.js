import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { singleLineTitle } from './taskTitle.js'

describe('singleLineTitle', () => {
  it('joins the lines of a pasted text with one space', () => {
    assert.equal(
      singleLineTitle('Relancer La Maroquinerie\npour la date\r\n  de décembre'),
      'Relancer La Maroquinerie pour la date de décembre'
    )
  })

  it('trims the ends, blank lines included', () => {
    assert.equal(singleLineTitle('\n  Imprimer les setlists  \n\n'), 'Imprimer les setlists')
  })

  it('keeps a title on one line as it is', () => {
    assert.equal(
      singleLineTitle('Changer les cordes de la basse'),
      'Changer les cordes de la basse'
    )
  })

  it('reads nothing as an empty title', () => {
    assert.equal(singleLineTitle(null), '')
    assert.equal(singleLineTitle('  \n '), '')
  })
})
