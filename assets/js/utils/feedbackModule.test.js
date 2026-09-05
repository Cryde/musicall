import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { BAND_SPACE_ROUTES } from '../constants/bandSpace.js'
import { FEEDBACK_MODULES, resolveFeedbackModule } from './feedbackModule.js'

describe('resolveFeedbackModule', () => {
  it('maps every Band Space route to its own module', () => {
    assert.equal(resolveFeedbackModule(BAND_SPACE_ROUTES.AGENDA), FEEDBACK_MODULES.AGENDA)
    assert.equal(resolveFeedbackModule(BAND_SPACE_ROUTES.NOTES), FEEDBACK_MODULES.NOTES)
    assert.equal(resolveFeedbackModule(BAND_SPACE_ROUTES.FILES), FEEDBACK_MODULES.FILE)
    assert.equal(resolveFeedbackModule(BAND_SPACE_ROUTES.TASKS), FEEDBACK_MODULES.TASK)
    assert.equal(resolveFeedbackModule(BAND_SPACE_ROUTES.FINANCE), FEEDBACK_MODULES.FINANCE)
    assert.equal(resolveFeedbackModule(BAND_SPACE_ROUTES.SETLIST), FEEDBACK_MODULES.SETLIST)
    assert.equal(resolveFeedbackModule(BAND_SPACE_ROUTES.RIDER), FEEDBACK_MODULES.RIDER)
    assert.equal(resolveFeedbackModule(BAND_SPACE_ROUTES.PARAMETERS), FEEDBACK_MODULES.SETTINGS)
    assert.equal(resolveFeedbackModule(BAND_SPACE_ROUTES.DASHBOARD), FEEDBACK_MODULES.DASHBOARD)
  })

  // The live view sits outside both layouts, so it is the one Band Space route that cannot be
  // reached from BAND_SPACE_ROUTES and has to be listed by hand.
  it('maps the standalone setlist live route to the setlist module', () => {
    assert.equal(resolveFeedbackModule('app_band_setlist_live'), FEEDBACK_MODULES.SETLIST)
  })

  it('maps the site sections', () => {
    assert.equal(resolveFeedbackModule('forum_topic_item'), FEEDBACK_MODULES.FORUM)
    assert.equal(resolveFeedbackModule('app_publication_show'), FEEDBACK_MODULES.PUBLICATION)
    assert.equal(resolveFeedbackModule('app_gallery_show'), FEEDBACK_MODULES.GALLERY)
    assert.equal(resolveFeedbackModule('app_search_drummer'), FEEDBACK_MODULES.DIRECTORY)
    assert.equal(resolveFeedbackModule('app_course_show'), FEEDBACK_MODULES.COURSE)
    assert.equal(resolveFeedbackModule('app_messages'), FEEDBACK_MODULES.MESSAGE)
  })

  // Matched by prefix: there are eight of them today and the list keeps growing.
  it('folds every settings route into the account module', () => {
    assert.equal(resolveFeedbackModule('app_user_settings'), FEEDBACK_MODULES.ACCOUNT)
    assert.equal(resolveFeedbackModule('app_user_settings_notifications'), FEEDBACK_MODULES.ACCOUNT)
    assert.equal(
      resolveFeedbackModule('app_user_settings_profile_teacher'),
      FEEDBACK_MODULES.ACCOUNT
    )
  })

  it('falls back to other for an unmapped route', () => {
    assert.equal(resolveFeedbackModule('app_home'), FEEDBACK_MODULES.OTHER)
    assert.equal(resolveFeedbackModule('not_found'), FEEDBACK_MODULES.OTHER)
  })

  // The drawer can open before the router has settled, and a null route name must not throw.
  it('falls back to other when there is no route name', () => {
    assert.equal(resolveFeedbackModule(null), FEEDBACK_MODULES.OTHER)
    assert.equal(resolveFeedbackModule(undefined), FEEDBACK_MODULES.OTHER)
    assert.equal(resolveFeedbackModule(''), FEEDBACK_MODULES.OTHER)
  })
})
