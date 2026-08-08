import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  BAND_SPACE_ROUTES,
  BAND_SPACE_SETTINGS_SECTIONS,
  filterSectionsByRole,
  NAVIGATION_ITEMS,
  resolveSettingsSection,
  visibleSettingsSections
} from './bandSpace.js'

/**
 * The role gating of the « Paramètres » page, which is why the section list and its two helpers
 * live here rather than inside Settings.vue: choosing what a role may see is arithmetic on an
 * array, and there is no component harness in this project to test the rendering itself.
 *
 * Run with `npm test`.
 */

describe('the Paramètres sidebar entry', () => {
  // The bug this file was written for: the entry used to be dropped for anyone who was not an
  // admin, which left a member with no way to reach « Quitter le Band Space » or their stage name.
  it('is a plain navigation item, hidden from nobody', () => {
    const parameters = NAVIGATION_ITEMS.find((item) => item.route === BAND_SPACE_ROUTES.PARAMETERS)

    assert.ok(parameters)
    assert.equal(parameters.superAdminOnly, undefined)
  })
})

describe('visibleSettingsSections', () => {
  it('gives an admin every section', () => {
    assert.deepEqual(visibleSettingsSections(true), [...BAND_SPACE_SETTINGS_SECTIONS])
  })

  it('never hands a member a section flagged admin only', () => {
    assert.ok(visibleSettingsSections(false).every((section) => !section.adminOnly))
  })

  it('leaves a member the member level sections', () => {
    const keys = visibleSettingsSections(false).map((section) => section.key)

    for (const key of ['members', 'activity', 'storage']) {
      assert.ok(keys.includes(key), `« ${key} » must stay reachable for a member`)
    }
  })
})

describe('resolveSettingsSection', () => {
  it('honours a deep link a member is allowed to follow', () => {
    // The pending deletion banner points here, and the dashboard activity widget at ?section=activity.
    assert.equal(resolveSettingsSection('danger', false), 'danger')
    assert.equal(resolveSettingsSection('activity', false), 'activity')
  })

  it('falls back to the first visible section without a query', () => {
    assert.equal(resolveSettingsSection(undefined, false), 'members')
    assert.equal(resolveSettingsSection(undefined, true), 'members')
  })

  it('falls back when the query names a section that does not exist', () => {
    assert.equal(resolveSettingsSection('does-not-exist', false), 'members')
  })

  it('refuses to land a member on an admin only section', () => {
    // Vacuous while nothing is flagged, and deliberately so: it starts biting the day a section is.
    for (const section of BAND_SPACE_SETTINGS_SECTIONS.filter((s) => s.adminOnly)) {
      assert.notEqual(resolveSettingsSection(section.key, false), section.key)
      assert.equal(resolveSettingsSection(section.key, true), section.key)
    }
  })

  it('hides an admin only section from a member', () => {
    // Synthetic sections, because the real ones carry no flag yet and this is the branch that
    // stops a member reaching a tab reserved for admins. An inverted predicate fails here.
    const sections = [
      { key: 'members', label: 'Membres', adminOnly: false },
      { key: 'billing', label: 'Facturation', adminOnly: true }
    ]

    assert.deepEqual(
      filterSectionsByRole(sections, false).map((section) => section.key),
      ['members']
    )
    assert.deepEqual(
      filterSectionsByRole(sections, true).map((section) => section.key),
      ['members', 'billing']
    )
  })

  it('always has a fallback to offer a member', () => {
    // The guarantee behind the assertions above: the first section is never admin only, so the
    // fallback can never come back empty.
    assert.equal(BAND_SPACE_SETTINGS_SECTIONS[0].adminOnly, false)
    assert.notEqual(resolveSettingsSection(undefined, false), null)
  })
})
