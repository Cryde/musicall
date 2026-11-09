import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { segmentedSeries } from './segmentedTimeSeries.js'

const DATES = ['2026-11-08', '2026-11-09', '2026-11-10']

describe('segmentedSeries', () => {
  it('gives each segment its count per day, in the order the segments are listed', () => {
    const points = [
      { date_label: '2026-11-08', segment: 'web', count: 3 },
      { date_label: '2026-11-08', segment: 'android', count: 1 },
      { date_label: '2026-11-10', segment: 'android', count: 2 }
    ]

    assert.deepEqual(segmentedSeries(points, DATES, ['web', 'android']), [
      { segment: 'web', data: [3, 0, 0], total: 3 },
      { segment: 'android', data: [1, 0, 2], total: 3 }
    ])
  })

  it('keeps a segment with no registration at all, at zero', () => {
    assert.deepEqual(segmentedSeries([], DATES, ['ios']), [
      { segment: 'ios', data: [0, 0, 0], total: 0 }
    ])
  })

  it('leaves out a point outside the dates or of a segment it was not asked for', () => {
    const points = [
      { date_label: '2026-11-01', segment: 'web', count: 5 },
      { date_label: '2026-11-09', segment: 'tv', count: 4 }
    ]

    assert.deepEqual(segmentedSeries(points, DATES, ['web']), [
      { segment: 'web', data: [0, 0, 0], total: 0 }
    ])
  })
})
