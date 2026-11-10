import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { cappedSegments, segmentedSeries } from './segmentedTimeSeries.js'

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

describe('cappedSegments', () => {
  const points = [
    { date_label: '2026-11-08', segment: 'google', count: 4 },
    { date_label: '2026-11-08', segment: 'www.facebook.com', count: 1 },
    { date_label: '2026-11-08', segment: 'l.instagram.com', count: 1 },
    { date_label: '2026-11-09', segment: '(direct)', count: 3 },
    { date_label: '2026-11-09', segment: 'www.facebook.com', count: 1 },
    { date_label: '2026-11-10', segment: 'forum.example.org', count: 2 },
    { date_label: '2026-11-10', segment: 'l.instagram.com', count: 1 }
  ]

  it('keeps the segments with the most counts and folds the rest, day by day', () => {
    assert.deepEqual(cappedSegments(points, 2, '(other)'), {
      segments: ['google', '(direct)', '(other)'],
      dataPoints: [
        { date_label: '2026-11-08', segment: 'google', count: 4 },
        { date_label: '2026-11-08', segment: '(other)', count: 2 },
        { date_label: '2026-11-09', segment: '(direct)', count: 3 },
        { date_label: '2026-11-09', segment: '(other)', count: 1 },
        { date_label: '2026-11-10', segment: '(other)', count: 3 }
      ]
    })
  })

  it('breaks a tie by name, so the same data always keeps the same segments', () => {
    // www.facebook.com, l.instagram.com and forum.example.org all total 2
    assert.deepEqual(cappedSegments(points, 3, '(other)').segments, [
      'google',
      '(direct)',
      'forum.example.org',
      '(other)'
    ])
  })

  it('folds nothing when the segments fit, ranked by count', () => {
    assert.deepEqual(cappedSegments(points, 5, '(other)'), {
      segments: ['google', '(direct)', 'forum.example.org', 'l.instagram.com', 'www.facebook.com'],
      dataPoints: points
    })
  })

  it('charts the capped series like any other', () => {
    const { segments, dataPoints } = cappedSegments(points, 1, '(other)')

    assert.deepEqual(segmentedSeries(dataPoints, DATES, segments), [
      { segment: 'google', data: [4, 0, 0], total: 4 },
      { segment: '(other)', data: [2, 4, 3], total: 9 }
    ])
  })

  it('has no segment without data', () => {
    assert.deepEqual(cappedSegments([], 5, '(other)'), { segments: [], dataPoints: [] })
  })
})
