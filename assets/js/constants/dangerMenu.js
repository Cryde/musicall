// PrimeVue colours an item's text and icon itself, unlayered, so only an important class turns them red.
const DANGER_TEXT = '!text-red-700 dark:!text-red-400'

/** The `pt` of a PrimeVue Menu whose items flagged `danger: true` read in red. */
export const DANGER_MENU_PT = Object.freeze({
  itemContent: ({ context }) => ({ class: context.item.danger ? DANGER_TEXT : undefined }),
  itemIcon: ({ context }) => ({ class: context.item.danger ? DANGER_TEXT : undefined })
})
