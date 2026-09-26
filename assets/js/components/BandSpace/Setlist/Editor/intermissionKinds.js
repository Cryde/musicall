/**
 * The three intermèdes of a running order (#1061), in the order the footer offers them. `label` is
 * also the default label of one created in one click, which the member renames afterwards.
 */
export const INTERMISSION_KINDS = [
  {
    type: 'break',
    label: 'Pause',
    tag: 'PAUSE',
    icon: 'pi pi-pause',
    color: 'text-amber-700 dark:text-amber-400'
  },
  {
    type: 'talk',
    label: 'MC',
    tag: 'MC',
    icon: 'pi pi-microphone',
    color: 'text-violet-700 dark:text-violet-300'
  },
  {
    type: 'interlude',
    label: 'Interlude',
    tag: 'INTERLUDE',
    icon: 'pi pi-volume-up',
    color: 'text-teal-700 dark:text-teal-300'
  }
]

export function intermissionKind(type) {
  return INTERMISSION_KINDS.find((kind) => kind.type === type) ?? INTERMISSION_KINDS[0]
}
