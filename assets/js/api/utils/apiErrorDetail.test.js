import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { apiErrorDetail } from './apiErrorDetail.js'

describe('apiErrorDetail', () => {
  it('shows the message a 4xx wrote for the user', () => {
    assert.equal(
      apiErrorDetail(
        { status: 422, message: 'Cette catégorie contient une entrée payée.' },
        'fallback'
      ),
      'Cette catégorie contient une entrée payée.'
    )
  })

  it('shows the message of a 403, which is the ownership refusal', () => {
    assert.equal(
      apiErrorDetail(
        {
          status: 403,
          message: 'Vous ne pouvez supprimer que vos propres récurrences personnelles'
        },
        'fallback'
      ),
      'Vous ne pouvez supprimer que vos propres récurrences personnelles'
    )
  })

  it('hides the technical message of a 5xx', () => {
    assert.equal(
      apiErrorDetail({ status: 500, message: 'Internal Server Error' }, 'fallback'),
      'fallback'
    )
  })

  it('hides a network failure, which carries no status at all', () => {
    assert.equal(apiErrorDetail({ message: 'Network Error' }, 'fallback'), 'fallback')
  })

  it('falls back on a 4xx with no message', () => {
    assert.equal(apiErrorDetail({ status: 404 }, 'fallback'), 'fallback')
  })

  it('falls back when there is no error object at all', () => {
    assert.equal(apiErrorDetail(null, 'fallback'), 'fallback')
    assert.equal(apiErrorDetail(undefined, 'fallback'), 'fallback')
  })
})
