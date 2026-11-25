import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { defaultEmailChoices, emailChoicesSummary, withEveryEmail } from './signupEmailChoices.js'

describe('defaultEmailChoices', () => {
  it('checks every email but the promotions', () => {
    assert.deepEqual(defaultEmailChoices(), {
      marketing: false,
      site_news: true,
      activity_reminder: true,
      weekly_recap: true,
      message_received: true,
      publication_comment: true,
      forum_reply: true,
      band_agenda_reminder: true
    })
  })

  it('hands out a new object each time, so one sign-up never edits the next', () => {
    const first = defaultEmailChoices()
    first.marketing = true

    assert.equal(defaultEmailChoices().marketing, false)
  })
})

describe('emailChoicesSummary', () => {
  it('reads the defaults as the defaults', () => {
    assert.deepEqual(emailChoicesSummary(defaultEmailChoices()), {
      kind: 'default',
      checked: 7,
      total: 8
    })
  })

  it('reads every email checked as all', () => {
    assert.deepEqual(emailChoicesSummary(withEveryEmail(defaultEmailChoices(), true)), {
      kind: 'all',
      checked: 8,
      total: 8
    })
  })

  it('reads nothing checked as none', () => {
    assert.deepEqual(emailChoicesSummary(withEveryEmail(defaultEmailChoices(), false)), {
      kind: 'none',
      checked: 0,
      total: 8
    })
  })

  it('counts any other choice', () => {
    const choices = { ...defaultEmailChoices(), site_news: false, weekly_recap: false }

    assert.deepEqual(emailChoicesSummary(choices), { kind: 'some', checked: 5, total: 8 })
  })

  it('counts a single email left checked', () => {
    const choices = { ...withEveryEmail(defaultEmailChoices(), false), message_received: true }

    assert.deepEqual(emailChoicesSummary(choices), { kind: 'some', checked: 1, total: 8 })
  })
})

describe('withEveryEmail', () => {
  it('sets every email and keeps the same switches', () => {
    assert.deepEqual(withEveryEmail({ marketing: false, site_news: true }, true), {
      marketing: true,
      site_news: true
    })
  })
})
