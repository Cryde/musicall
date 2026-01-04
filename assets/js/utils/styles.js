export const MAX_VISIBLE_STYLES = 5

export function formatStyles(styles, limit = MAX_VISIBLE_STYLES) {
  const styleNames = styles.map((style) => style.name.toLocaleLowerCase())
  const visible = styleNames.slice(0, limit)
  const remaining = styleNames.length - limit

  return {
    visible: visible.join(', '),
    remaining,
    all: styleNames.join(', ')
  }
}

export function hasMoreStyles(styles, limit = MAX_VISIBLE_STYLES) {
  return styles.length > limit
}
