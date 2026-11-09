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
