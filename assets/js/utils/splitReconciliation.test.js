import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { planSplitSync, totalOfSplits } from './splitReconciliation.js'

/**
 * These pin the split editor's save, which used to destroy money during an ordinary edit: raising one
 * member's share deleted their split, the re-create was refused for busting the entry total, and the
 * drawer closed on a green "Entrée enregistrée" with the old share gone.
 *
 * Run with `npm test`. The Vue wiring around this has no coverage, which is why the whole decision
 * lives here rather than inside the component.
 */

/** A split as the collection returns it. */
function existing(id, memberId, amount) {
  return { id, member_id: memberId, amount }
}

/** A split as the drawer's inputs describe it. */
function desired(memberId, amount) {
  return { member_id: memberId, amount }
}

describe('totalOfSplits', () => {
  it('adds nothing up to zero', () => {
    assert.equal(totalOfSplits([]), 0)
  })

  it('adds the shares in cents', () => {
    assert.equal(totalOfSplits([desired('a', 5000), desired('b', 2550)]), 7550)
  })
})

describe('planSplitSync', () => {
  it('does nothing when the splits already match', () => {
    const splits = [existing('s1', 'a', 5000), existing('s2', 'b', 5000)]

    assert.deepEqual(planSplitSync(splits, [desired('a', 5000), desired('b', 5000)], 10000), {
      error: null,
      operations: []
    })
  })

  it('creates the splits of an entry that had none', () => {
    assert.deepEqual(planSplitSync([], [desired('a', 4000)], 10000), {
      error: null,
      operations: [{ type: 'create', memberId: 'a', amount: 4000 }]
    })
  })

  it('deletes the split of a member dropped from the repartition', () => {
    assert.deepEqual(planSplitSync([existing('s1', 'a', 5000)], [], 10000), {
      error: null,
      operations: [{ type: 'delete', splitId: 's1', memberId: 'a' }]
    })
  })

  it('replaces a changed share, deleting before creating because the API refuses a second split for one member', () => {
    assert.deepEqual(planSplitSync([existing('s1', 'a', 5000)], [desired('a', 7000)], 10000), {
      error: null,
      operations: [
        { type: 'delete', splitId: 's1', memberId: 'a' },
        { type: 'create', memberId: 'a', amount: 7000 }
      ]
    })
  })

  it('leaves an untouched member alone while another one changes', () => {
    const splits = [existing('s1', 'a', 5000), existing('s2', 'b', 5000)]
    const { operations } = planSplitSync(splits, [desired('a', 3000), desired('b', 5000)], 10000)

    assert.deepEqual(operations, [
      { type: 'delete', splitId: 's1', memberId: 'a' },
      { type: 'create', memberId: 'a', amount: 3000 }
    ])
  })

  it('runs every delete before any create, so no create is ever refused by a share about to go', () => {
    const splits = [existing('s1', 'a', 6000), existing('s2', 'b', 4000)]
    const { operations } = planSplitSync(splits, [desired('a', 4000), desired('b', 6000)], 10000)

    const lastDelete = operations.findLastIndex((operation) => operation.type === 'delete')
    const firstCreate = operations.findIndex((operation) => operation.type === 'create')

    assert.equal(operations.length, 4)
    assert.ok(lastDelete < firstCreate)
  })

  // The bug this module exists for: entry 100, A=50 and B=50, A bumped to 70 and saved.
  it('refuses a repartition above the entry amount instead of deleting a share it cannot re-create', () => {
    const splits = [existing('s1', 'a', 5000), existing('s2', 'b', 5000)]
    const plan = planSplitSync(splits, [desired('a', 7000), desired('b', 5000)], 10000)

    assert.deepEqual(plan.operations, [])
    assert.equal(
      plan.error,
      'Le total des répartitions (120,00 €) dépasse le montant de l’entrée (100,00 €).'
    )
  })

  /**
   * Lowering the amount of an entry whose shares nobody touched: there is nothing to write, so there
   * is nothing to refuse. The entry keeps the mismatch it already shows, which is what happened before
   * and is not what this module is here to stop.
   */
  it('lets an unchanged repartition stand even once it exceeds the entry amount', () => {
    const splits = [existing('s1', 'a', 5000), existing('s2', 'b', 5000)]
    const plan = planSplitSync(splits, [desired('a', 5000), desired('b', 5000)], 8000)

    assert.equal(plan.error, null)
    assert.deepEqual(plan.operations, [])
  })

  it('still refuses as soon as one share moves under a lowered amount', () => {
    const splits = [existing('s1', 'a', 5000), existing('s2', 'b', 5000)]
    const plan = planSplitSync(splits, [desired('a', 6000), desired('b', 5000)], 8000)

    assert.deepEqual(plan.operations, [])
    assert.equal(
      plan.error,
      'Le total des répartitions (110,00 €) dépasse le montant de l’entrée (80,00 €).'
    )
  })

  it('accepts a repartition landing exactly on the entry amount', () => {
    const plan = planSplitSync([], [desired('a', 6000), desired('b', 4000)], 10000)

    assert.equal(plan.error, null)
    assert.equal(plan.operations.length, 2)
  })

  it('accepts a partial repartition, which is a normal way to use the feature', () => {
    const plan = planSplitSync([], [desired('a', 3000)], 10000)

    assert.equal(plan.error, null)
    assert.deepEqual(plan.operations, [{ type: 'create', memberId: 'a', amount: 3000 }])
  })

  // A fourchette or an empty amount stores NULL, and the API caps nothing against NULL.
  it('caps nothing when the entry carries no exact amount', () => {
    const plan = planSplitSync([], [desired('a', 900000)], null)

    assert.equal(plan.error, null)
    assert.equal(plan.operations.length, 1)
  })

  /**
   * The property that makes deleting first safe: replay the plan against the API's own rule, which
   * refuses a create whose amount would push the total held past the entry amount.
   */
  it('never trips the running total the API checks each create against', () => {
    const capCents = 10000
    const splits = [existing('s1', 'a', 2000), existing('s2', 'b', 3000), existing('s3', 'c', 1000)]
    const target = [desired('a', 5000), desired('b', 3000), desired('d', 2000)]

    const { error, operations } = planSplitSync(splits, target, capCents)
    assert.equal(error, null)

    const held = new Map(splits.map((split) => [split.id, split.amount]))
    for (const operation of operations) {
      if (operation.type === 'delete') {
        held.delete(operation.splitId)
        continue
      }
      const total = [...held.values()].reduce((sum, amount) => sum + amount, 0)
      assert.ok(
        total + operation.amount <= capCents,
        `create of ${operation.amount} on a held total of ${total} would be refused`
      )
      held.set(`created-${operation.memberId}`, operation.amount)
    }

    assert.equal(
      [...held.values()].reduce((sum, amount) => sum + amount, 0),
      10000
    )
  })
})
