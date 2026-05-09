/**
 * Drag-and-drop helpers for the folder tree + file list.
 *
 * Drag sources:
 *   - folder: { type: 'folder', id, parentId, descendantIds: string[] }
 *   - file:   { type: 'file', id, folderId }
 *
 * Drop targets:
 *   - any folder in the tree
 *   - the "Tous les fichiers" root (folderId = null)
 *
 * Validation:
 *   - folder cannot drop on itself or a descendant (cycle)
 *   - folder cannot drop on its current parent (no-op)
 *   - file cannot drop on its current folder (no-op)
 */

/**
 * Walk a nested folder tree and return the ids of $folderId and every descendant.
 *
 * @param {Array} tree
 * @param {string} folderId
 * @returns {string[]}
 */
export function collectFolderAndDescendants(tree, folderId) {
  const ids = []
  const walk = (nodes, found = false) => {
    for (const node of nodes) {
      const isMatch = found || node.id === folderId
      if (isMatch) {
        ids.push(node.id)
      }
      if (Array.isArray(node.children) && node.children.length > 0) {
        walk(node.children, isMatch)
      }
    }
  }
  walk(tree)
  return ids
}

/**
 * Whether a drop is allowed for the given source on the given target folder id.
 *
 * @param {object|null} source
 * @param {string|null} targetFolderId  null = root
 * @returns {boolean}
 */
export function canDrop(source, targetFolderId) {
  if (!source) return false

  if (source.type === 'folder') {
    if (Array.isArray(source.descendantIds) && source.descendantIds.includes(targetFolderId)) {
      return false
    }
    if ((source.parentId ?? null) === (targetFolderId ?? null)) {
      return false
    }
    return true
  }

  if (source.type === 'file') {
    return (source.folderId ?? null) !== (targetFolderId ?? null)
  }

  return false
}
