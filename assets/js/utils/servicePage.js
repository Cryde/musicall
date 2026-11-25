/** What every kind's page sums up the same way (#1410, #1411), kept out of the components so it is tested. */

/** The specialities picked, in the order the kind lists them (`all`), whatever order they were ticked in. */
export function orderedSpecialities(all, picked) {
  return all.filter((speciality) => picked?.includes(speciality))
}
