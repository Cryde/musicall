import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  canReportContent,
  idFromIri,
  reportContentLink,
  reportExcerpt,
  reportLiveState,
  reportOutcomeLabel,
  reportReasonLabel,
  reportTargetTypeLabel
} from './reportTarget.js'

describe('labels', () => {
  it('names every reason and target type the server knows', () => {
    assert.equal(reportReasonLabel('fake'), 'Faux profil ou arnaque')
    assert.equal(reportReasonLabel('harassment'), 'Harcèlement')
    assert.equal(reportTargetTypeLabel('band_chat_message'), 'Discussion de groupe')
    assert.equal(reportTargetTypeLabel('forum_post'), 'Message du forum')
  })

  it('falls back to the raw value for something it does not know', () => {
    assert.equal(reportReasonLabel('new_reason'), 'new_reason')
    assert.equal(reportTargetTypeLabel('new_type'), 'new_type')
  })

  it('reads the live state and the outcome', () => {
    assert.deepEqual(reportLiveState('edited'), { label: 'Modifié depuis', severity: 'warn' })
    assert.equal(reportLiveState(null), null)
    assert.equal(reportOutcomeLabel('account_suspended'), 'Compte suspendu')
    assert.equal(reportOutcomeLabel(null), null)
  })
})

describe('reportExcerpt', () => {
  it('flattens whitespace and cuts long text', () => {
    assert.equal(reportExcerpt('a\n\n  b'), 'a b')
    assert.equal(reportExcerpt('abcdefghij', 5), 'abcd…')
    assert.equal(reportExcerpt(null), '')
  })
})

describe('idFromIri', () => {
  it('takes the last segment', () => {
    assert.equal(idFromIri('/api/messages/0199-abc'), '0199-abc')
    assert.equal(idFromIri(undefined), '')
  })
})

describe('canReportContent', () => {
  const viewer = { id: 'u1', username: 'alice' }

  it('refuses your own content, by username or by id', () => {
    assert.equal(canReportContent(viewer, { username: 'alice' }), false)
    assert.equal(canReportContent(viewer, { id: 'u1' }), false)
  })

  it('offers it on somebody else content', () => {
    assert.equal(canReportContent(viewer, { username: 'bob' }), true)
    assert.equal(canReportContent(viewer, { id: 'u2' }), true)
  })

  it('waits for the profile when only the author id is known', () => {
    assert.equal(canReportContent({ id: null, username: 'alice' }, { id: 'u2' }), false)
  })

  it('refuses a visitor', () => {
    assert.equal(canReportContent({ id: null, username: null }, { username: 'bob' }), false)
  })
})

describe('reportContentLink', () => {
  function report(target_type, snapshot_context, target_id = 'target-1') {
    return { target_type, target_id, snapshot_context }
  }

  it('points a profile and an announce at the public profile', () => {
    const expected = { name: 'app_user_public_profile', params: { username: 'bob' } }
    assert.deepEqual(reportContentLink(report('user', { username: 'bob' })).to, expected)
    assert.deepEqual(
      reportContentLink(report('announce', { instrument: 'Batterie', username: 'bob' })).to,
      expected
    )
  })

  it('points a media at the profile it belongs to', () => {
    assert.deepEqual(
      reportContentLink(report('profile_media', { profile: 'teacher', username: 'bob' })).to,
      { name: 'app_user_teacher_profile', params: { username: 'bob' } }
    )
    assert.deepEqual(
      reportContentLink(report('profile_media', { profile: 'musician', username: 'bob' })).to,
      { name: 'app_user_musician_profile', params: { username: 'bob' } }
    )
  })

  it('points a forum post at its page and anchor', () => {
    assert.deepEqual(
      reportContentLink(report('forum_post', { topic_slug: 'amp', topic_page: 3 }, 'p9')).to,
      { name: 'forum_topic_item', params: { slug: 'amp', page: '3' }, hash: '#post-p9' }
    )
    assert.deepEqual(
      reportContentLink(report('forum_post', { topic_slug: 'amp', topic_page: 1 }, 'p9')).to,
      { name: 'forum_topic_item', params: { slug: 'amp' }, hash: '#post-p9' }
    )
  })

  it('points a publication or a course at its page, and a comment at its publication', () => {
    assert.deepEqual(
      reportContentLink(report('publication', { publication_slug: 'x', is_course: true })).to,
      { name: 'app_course_show', params: { slug: 'x' } }
    )
    assert.deepEqual(
      reportContentLink(report('publication', { publication_slug: 'x', is_course: false })).to,
      { name: 'app_publication_show', params: { slug: 'x' } }
    )
    assert.deepEqual(reportContentLink(report('comment', { publication_slug: 'x' })).to, {
      name: 'app_publication_show',
      params: { slug: 'x' }
    })
    assert.deepEqual(
      reportContentLink(report('comment', { publication_slug: 'x', is_course: true })).to,
      {
        name: 'app_course_show',
        params: { slug: 'x' }
      }
    )
  })

  it('never links a private conversation or a band discussion', () => {
    assert.deepEqual(reportContentLink(report('message', { thread_id: 't1' })), {
      to: null,
      text: 'Conversation privée'
    })
    assert.deepEqual(
      reportContentLink(
        report('band_chat_message', { band_space_id: 'b', band_space_name: 'Les Cactus' })
      ),
      { to: null, text: 'Discussion du groupe Les Cactus' }
    )
  })

  it('has no link when the context lacks what the route needs', () => {
    assert.equal(reportContentLink(report('user', {})).to, null)
    assert.equal(reportContentLink({ target_type: 'forum_post', target_id: 'p' }).to, null)
  })
})
