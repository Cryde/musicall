import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { categoryDeleteMessage, RECURRENCE_DELETE_MESSAGE } from './financeConfirmations.js'

describe('categoryDeleteMessage', () => {
  it('says an empty category takes nothing with it', () => {
    assert.equal(
      categoryDeleteMessage(0),
      'Es-tu sûr de vouloir supprimer cette catégorie ? Elle ne contient aucune entrée.'
    )
  })

  it('treats a missing count as an empty category rather than printing undefined', () => {
    assert.equal(categoryDeleteMessage(undefined), categoryDeleteMessage(0))
    assert.equal(categoryDeleteMessage(null), categoryDeleteMessage(0))
  })

  it('agrees in the singular', () => {
    assert.equal(
      categoryDeleteMessage(1),
      'Es-tu sûr de vouloir supprimer cette catégorie ? L’entrée qu’elle contient et ses récurrences seront également supprimées.'
    )
  })

  it('names the number of entries the delete destroys', () => {
    assert.equal(
      categoryDeleteMessage(12),
      'Es-tu sûr de vouloir supprimer cette catégorie ? Les 12 entrées qu’elle contient et ses récurrences seront également supprimées.'
    )
  })

  it('says a category holding sub-categories cannot be deleted at all', () => {
    // The server refuses this outright, and a category with children can hold no entries of its own,
    // so the old copy promised a deletion and then failed for a reason it had never raised.
    const refused =
      'Cette catégorie contient des sous-catégories et ne peut pas être supprimée. Supprimez ou déplacez ses sous-catégories d’abord.'

    assert.equal(categoryDeleteMessage(0, true), refused)
    assert.equal(categoryDeleteMessage(12, true), refused)
  })

  it('describes the entries again once there are no sub-categories', () => {
    assert.equal(categoryDeleteMessage(12, false), categoryDeleteMessage(12))
  })
})

describe('RECURRENCE_DELETE_MESSAGE', () => {
  it('says the forecasts go and the committed or paid occurrences stay', () => {
    assert.match(RECURRENCE_DELETE_MESSAGE, /entrées prévues/)
    assert.match(RECURRENCE_DELETE_MESSAGE, /engagées ou payées seront conservées/)
  })
})
