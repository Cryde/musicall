import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { bandInitial, userMenuEntries } from './userMenu.js'

describe('userMenuEntries', () => {
  it('lists a member their own pages, the profile first and the settings last', () => {
    const entries = userMenuEntries({ username: 'lea', isAdmin: false })

    assert.deepEqual(
      entries.map(({ key }) => key),
      ['profile', 'announces', 'publications', 'courses', 'photos', 'settings']
    )
  })

  it('adds the administration after the settings, for an admin only', () => {
    const entries = userMenuEntries({ username: 'lea', isAdmin: true })

    assert.deepEqual(entries.at(-1), {
      key: 'admin',
      labelKey: 'user_menu.admin',
      icon: 'pi pi-shield',
      to: { name: 'admin_dashboard' }
    })
  })

  it('opens the profile of the member it is built for', () => {
    const [profile] = userMenuEntries({ username: 'lea', isAdmin: false })

    assert.deepEqual(profile.to, { name: 'app_user_public_profile', params: { username: 'lea' } })
  })

  it('names every entry with a key of the account menu and gives it an icon', () => {
    for (const entry of userMenuEntries({ username: 'lea', isAdmin: true })) {
      assert.match(entry.labelKey, /^user_menu\.\w+$/)
      assert.match(entry.icon, /^pi pi-[a-z-]+$/)
    }
  })
})

describe('bandInitial', () => {
  it('takes the first letter of the name, in capitals', () => {
    assert.equal(bandInitial('les Lundis Jazz'), 'L')
  })

  it('skips the spaces before the name', () => {
    assert.equal(bandInitial('  écho'), 'É')
  })

  it('keeps a character outside the basic plane whole', () => {
    assert.equal(bandInitial('🎸 Riffs'), '🎸')
  })

  it('gives nothing for a missing or blank name', () => {
    assert.equal(bandInitial(''), '')
    assert.equal(bandInitial('   '), '')
    assert.equal(bandInitial(null), '')
  })
})
