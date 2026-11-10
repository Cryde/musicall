/**
 * Turns a segmented time series (one point per day and segment, days without any left out) into one
 * row of daily counts per segment, zero filled over every date of the range and in the order given.
 */
export function segmentedSeries(dataPoints, allDates, segments) {
  const counts = new Map(
    dataPoints.map((point) => [`${point.segment}|${point.date_label}`, point.count])
  )

  return segments.map((segment) => {
    const data = allDates.map((date) => counts.get(`${segment}|${date}`) ?? 0)
    return { segment, data, total: data.reduce((sum, count) => sum + count, 0) }
  })
}

/**
 * Keeps the `limit` segments with the most counts over the range and folds the rest into `otherSegment`, so
 * a long tail of referring sites stays one readable bar (#1369). Ties go to the first name, for a stable order.
 * Returns the segments in that order, the folded one last, and the data points to chart them with.
 */
export function cappedSegments(dataPoints, limit, otherSegment) {
  const totals = new Map()
  for (const point of dataPoints) {
    totals.set(point.segment, (totals.get(point.segment) ?? 0) + point.count)
  }
  const ranked = [...totals.keys()].sort(
    (a, b) => totals.get(b) - totals.get(a) || a.localeCompare(b)
  )
  if (ranked.length <= limit) {
    return { segments: ranked, dataPoints }
  }

  const kept = new Set(ranked.slice(0, limit))
  const folded = new Map()
  for (const point of dataPoints) {
    const segment = kept.has(point.segment) ? point.segment : otherSegment
    const key = `${segment}|${point.date_label}`
    const count = (folded.get(key)?.count ?? 0) + point.count
    folded.set(key, { date_label: point.date_label, segment, count })
  }

  return { segments: [...kept, otherSegment], dataPoints: [...folded.values()] }
}
