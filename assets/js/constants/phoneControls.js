// The phone screens' shared controls, so a chip or a sheet choice looks the same in every module.

/** A filter chip under a phone screen's head. */
export const PHONE_CHIP =
  'inline-flex h-9 shrink-0 items-center gap-1.5 rounded-full border px-3.5 text-sm font-medium transition-colors'
export const PHONE_CHIP_ON =
  'border-primary bg-primary-50 text-primary-800 dark:bg-primary-950 dark:text-primary-200'
export const PHONE_CHIP_OFF =
  'border-surface-300 bg-surface-0 text-surface-700 dark:border-surface-600 dark:bg-surface-900 dark:text-surface-200'

/** One choice of a bottom sheet, ticked when it is the current one. */
export const SHEET_CHOICE =
  'flex w-full items-center gap-3 px-5 py-3.5 text-left text-surface-900 transition-colors hover:bg-surface-100 dark:text-surface-0 dark:hover:bg-surface-800'
