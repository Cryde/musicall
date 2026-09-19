/**
 * The corner treatment that makes a block read as one stack rather than separate bubbles (#1031).
 *
 * Only the corners facing a neighbour are flattened, on the side the block is aligned to, so the
 * outside of the stack keeps its full radius. Messenger and iMessage both do this.
 *
 * Every class here is written out in full: Tailwind scans source text, so a name built by
 * concatenation is never generated and the corner silently stays round.
 */
export function bubbleCornerClasses(index, total, alignRight) {
  if (total <= 1) {
    return ''
  }

  const isFirst = index === 0
  const isLast = index === total - 1

  if (alignRight) {
    if (isFirst) {
      return 'rounded-br-sm'
    }

    return isLast ? 'rounded-tr-sm' : 'rounded-tr-sm rounded-br-sm'
  }

  if (isFirst) {
    return 'rounded-bl-sm'
  }

  return isLast ? 'rounded-tl-sm' : 'rounded-tl-sm rounded-bl-sm'
}
