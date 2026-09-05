import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { FEEDBACK_MODULES } from '../utils/feedbackModule.js'
import { FEEDBACK_MODULE_GROUPS, FEEDBACK_TYPE_OPTIONS } from './feedback.js'

const moduleValues = Object.values(FEEDBACK_MODULES)
const pickerOptions = FEEDBACK_MODULE_GROUPS.flatMap((group) => group.items)

describe('FEEDBACK_MODULE_GROUPS', () => {
  // Asserted here rather than in the PHP mirror test, which could only count
  // `FEEDBACK_MODULES.SOMETHING` identifiers in the source: a typo resolves to undefined at runtime
  // without throwing, so the count stayed right while the option was broken.
  it('gives every option a value that really exists on FEEDBACK_MODULES', () => {
    for (const option of pickerOptions) {
      assert.ok(
        moduleValues.includes(option.value),
        `Option « ${option.label} » has value ${JSON.stringify(option.value)}, which is not a FEEDBACK_MODULES value`
      )
    }
  })

  it('offers every module, so no route can prefill an option the user cannot see', () => {
    const offered = pickerOptions.map((option) => option.value).sort()
    assert.deepEqual(offered, [...moduleValues].sort())
  })

  it('labels every option', () => {
    for (const option of pickerOptions) {
      assert.equal(typeof option.label, 'string')
      assert.ok(option.label.length > 0)
    }
  })
})

describe('FEEDBACK_TYPE_OPTIONS', () => {
  it('gives every option a non-empty value and label', () => {
    for (const option of FEEDBACK_TYPE_OPTIONS) {
      assert.equal(typeof option.value, 'string')
      assert.ok(option.value.length > 0)
      assert.equal(typeof option.label, 'string')
      assert.ok(option.label.length > 0)
    }
  })
})
