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
 * The direct children of a folder in a nested tree, or the roots when at the top.
 *
 * The folder collection returns roots carrying their whole subtree under `children`, so the children of
 * any folder are already in memory and need no request. Used to list folders inline in the file panel.
 *
 * @param {Array} tree
 * @param {string|null} folderId  null = root level
 * @returns {Array}
 */
export function directChildren(tree, folderId) {
  if (!Array.isArray(tree)) return []
  if (!folderId) return tree

  const walk = (nodes) => {
    for (const node of nodes) {
      if (node.id === folderId) {
        return Array.isArray(node.children) ? node.children : []
      }
      if (Array.isArray(node.children) && node.children.length > 0) {
        const found = walk(node.children)
        if (found !== null) return found
      }
    }
    return null
  }

  return walk(tree) ?? []
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

/**
 * Perform a validated drop: reparent a folder, or move a file into a folder.
 *
 * Lives here rather than in a component because both drop surfaces need it, the sidebar tree and the
 * folder rows in the file panel. Always ends the drag, including on failure, so a rejected move cannot
 * leave the store thinking a drag is still in progress.
 *
 * @param {object} source
 * @param {string|null} targetFolderId  null = root
 * @param {{bandSpaceId: string, filesStore: object, filesApi: object, toast: object}} context
 */
export async function applyMove(
  source,
  targetFolderId,
  { bandSpaceId, filesStore, filesApi, toast }
) {
  if (!bandSpaceId) return

  try {
    if (source.type === 'folder') {
      await filesStore.updateFolder(bandSpaceId, source.id, { parent_id: targetFolderId })
      toast.add({ severity: 'success', summary: 'Dossier déplacé', life: 2500 })
    } else if (source.type === 'file') {
      await filesApi.updateFile(bandSpaceId, source.id, { folder_id: targetFolderId })
      filesStore.fetchFiles(bandSpaceId)
      toast.add({ severity: 'success', summary: 'Fichier déplacé', life: 2500 })
    }
  } catch (e) {
    toast.add({
      severity: 'error',
      summary: 'Déplacement impossible',
      detail: e.message,
      life: 5000
    })
  } finally {
    filesStore.endDrag()
  }
}
