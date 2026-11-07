import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { SKILL_STEP_COUNT, skillSteps } from './skillLevel.js'

describe('skillSteps', () => {
  it('fills one bar per level, up to all of them', () => {
    assert.equal(skillSteps('beginner'), 1)
    assert.equal(skillSteps('intermediate'), 2)
    assert.equal(skillSteps('advanced'), 3)
    assert.equal(skillSteps('professional'), SKILL_STEP_COUNT)
  })

  it('fills none for a level it does not know', () => {
    assert.equal(skillSteps(undefined), 0)
    assert.equal(skillSteps('virtuoso'), 0)
  })
})
