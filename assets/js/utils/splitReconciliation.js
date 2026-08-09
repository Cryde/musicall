import { formatAmount } from './currency.js'

/**
 * Turns the split amounts typed in the drawer into the sequence of API calls that gets the entry from
 * the splits it has to the splits it should have, or refuses the whole thing before anything is touched.
 *
 * The refusal is the point. There is no endpoint that edits a split: changing one member's share means
 * deleting the split and creating it again, and the create is rejected when the running total would pass
 * the entry amount. So an entry of 100 with A=50 and B=50, saved with A bumped to 70, used to delete A,
 * be refused the re-create at 50 + 70 > 100, and leave A's 50 gone for good. Nothing checked the target
 * set first, and the drawer closed on a success message.
 *
 * Deleting before creating is not a choice: two splits for the same member on the same entry are a
 * conflict, so a share can only be replaced after the old one is gone. What makes that safe is checking
 * the total up front. Once the desired total fits, every intermediate total does too, because the
 * untouched splits are already counted and each create only adds a positive amount on the way to a sum
 * that fits. The window where a share is missing still exists, which is why the caller has to treat a
 * failure as an error worth showing rather than a warning worth swallowing.
 */

/**
 * @typedef {{id: string, member_id: string, amount: number}} ExistingSplit
 * @typedef {{member_id: string, amount: number}} DesiredSplit
 * @typedef {{type: 'delete', splitId: string, memberId: string}
 *          |{type: 'create', memberId: string, amount: number}} SplitOperation
 */

/**
 * @param {DesiredSplit[]|ExistingSplit[]} splits
 * @returns {number} total in cents
 */
export function totalOfSplits(splits) {
  return splits.reduce((total, split) => total + split.amount, 0)
}

/**
 * @param {ExistingSplit[]} existingSplits splits the entry currently carries
 * @param {DesiredSplit[]} desiredSplits splits it should carry, already filtered to positive amounts
 * @param {number|null} capCents the entry's exact amount, which the API caps the split total at; null
 *                               when the entry carries a fourchette or no amount, where there is no cap
 * @returns {{error: string|null, operations: SplitOperation[]}} operations to run in order, and an
 *          error that means run none of them
 */
export function planSplitSync(existingSplits, desiredSplits, capCents = null) {
  const desiredByMember = new Map(desiredSplits.map((split) => [split.member_id, split.amount]))
  const existingByMember = new Map(existingSplits.map((split) => [split.member_id, split.amount]))

  const operations = []

  for (const existing of existingSplits) {
    if (desiredByMember.get(existing.member_id) !== existing.amount) {
      operations.push({ type: 'delete', splitId: existing.id, memberId: existing.member_id })
    }
  }

  for (const desired of desiredSplits) {
    if (existingByMember.get(desired.member_id) !== desired.amount) {
      operations.push({ type: 'create', memberId: desired.member_id, amount: desired.amount })
    }
  }

  // Nothing to write means nothing to lose. Lowering an entry's amount under a repartition nobody
  // touched keeps the mismatch the total line already shows in orange, rather than making an ordinary
  // amount edit impossible until the shares are redone.
  if (operations.length === 0) {
    return { error: null, operations }
  }

  const desiredTotal = totalOfSplits(desiredSplits)
  if (capCents != null && desiredTotal > capCents) {
    return {
      error: `Le total des répartitions (${formatAmount(desiredTotal)}) dépasse le montant de l’entrée (${formatAmount(capCents)}).`,
      operations: []
    }
  }

  return { error: null, operations }
}
