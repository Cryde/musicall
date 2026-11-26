import { intlLocale } from '../i18n/formatLocale.js'
import { fileSongs } from './songFileCounts.js'

/**
 * Folders inside a song's files (#1451). Virtual: a file keeps its place in Files, its song attachment
 * only says which of the song's folders it sits in.
 */

/** Levels below the song, as SongFileFolder::MAX_DEPTH: `Stems › Batterie › Micros` and no deeper. */
export const SONG_FOLDER_MAX_DEPTH = 3

function byName(a, b) {
  return a.name.localeCompare(b.name, intlLocale(), { sensitivity: 'base' })
}

/**
 * The folder a file sits in inside one song, read off that song's attachment: null at the song's root.
 *
 * @param {{attachments?: ReadonlyArray<{source_type: string, source_id: string, song_folder_id?: string|null}>}} file
 * @param {string} songId
 * @returns {string|null}
 */
export function songFolderIdOf(file, songId) {
  return fileSongs(file).find((song) => song.id === songId)?.folderId ?? null
}

/**
 * A song's folders and files as a tree: each node holds its sub-folders, its own files and how many files
 * it holds in all, sub-folders included. A file whose folder is not in `folders` (deleted by another
 * member a moment ago) sits at the root, where the server has put it.
 *
 * @param {ReadonlyArray<{id: string, parent_id: string|null, name: string}>} folders
 * @param {ReadonlyArray<Object>} files
 * @param {string} songId
 * @returns {{folders: Array<SongFolderNode>, files: Array<Object>}}
 *
 * @typedef {{folder: Object, depth: number, folders: Array<SongFolderNode>, files: Array<Object>, fileCount: number}} SongFolderNode
 */
export function buildSongFileTree(folders, files, songId) {
  const known = new Set(folders.map((folder) => folder.id))
  const filesByFolder = new Map()
  const rootFiles = []
  for (const file of files) {
    const folderId = songFolderIdOf(file, songId)
    if (folderId !== null && known.has(folderId)) {
      if (!filesByFolder.has(folderId)) filesByFolder.set(folderId, [])
      filesByFolder.get(folderId).push(file)
    } else {
      rootFiles.push(file)
    }
  }

  function nodesUnder(parentId, depth) {
    return folders
      .filter((folder) => (folder.parent_id ?? null) === parentId)
      .sort(byName)
      .map((folder) => {
        const children = nodesUnder(folder.id, depth + 1)
        const ownFiles = filesByFolder.get(folder.id) ?? []

        return {
          folder,
          depth,
          folders: children,
          files: ownFiles,
          fileCount: ownFiles.length + children.reduce((sum, child) => sum + child.fileCount, 0)
        }
      })
  }

  return { folders: nodesUnder(null, 1), files: rootFiles }
}

/**
 * The way down to a folder, the song's root first: what a breadcrumb or a dialog title names.
 *
 * @param {ReadonlyArray<{id: string, parent_id: string|null, name: string}>} folders
 * @param {string|null} folderId
 * @returns {Array<{id: string, name: string}>} empty for the root or an unknown folder
 */
export function songFolderPath(folders, folderId) {
  const byId = new Map(folders.map((folder) => [folder.id, folder]))
  const path = []
  for (let folder = byId.get(folderId); folder; folder = byId.get(folder.parent_id)) {
    path.unshift({ id: folder.id, name: folder.name })
    if (path.length > SONG_FOLDER_MAX_DEPTH) break
  }

  return path
}

/**
 * Whether a folder may hold another one, from its own depth (1 for a folder at the song's root).
 *
 * @param {number} depth
 * @returns {boolean}
 */
export function canHoldSongSubfolder(depth) {
  return depth < SONG_FOLDER_MAX_DEPTH
}

/**
 * Every place a song's file can go, in the order the tree shows them: the song's root first, then each
 * folder under its parent, with its depth for the indent.
 *
 * @param {ReadonlyArray<{id: string, parent_id: string|null, name: string}>} folders
 * @returns {Array<{id: string, name: string, depth: number}>} the folders only; the root is the caller's
 */
export function songFolderDestinations(folders) {
  const destinations = []
  function walk(nodes) {
    for (const node of nodes) {
      destinations.push({ id: node.folder.id, name: node.folder.name, depth: node.depth })
      walk(node.folders)
    }
  }
  walk(buildSongFileTree(folders, [], '').folders)

  return destinations
}

/**
 * The names offered while naming a folder: those already used across the space, so « Stems » is spelled
 * the same on every song, then the suggested ones. Each once whatever its case, without the names its
 * future siblings already have, narrowed to what was typed.
 *
 * @param {ReadonlyArray<string>} usedNames names of every song folder of the space
 * @param {ReadonlyArray<string>} suggestedNames
 * @param {ReadonlyArray<string>} siblingNames names already taken where the folder will go
 * @param {string} query
 * @returns {string[]}
 */
export function songFolderNameOptions(usedNames, suggestedNames, siblingNames, query) {
  const taken = new Set(siblingNames.map((name) => name.toLocaleLowerCase()))
  const needle = query.trim().toLocaleLowerCase()
  const seen = new Set()
  const options = []
  const sortedUsed = [...usedNames].sort((a, b) =>
    a.localeCompare(b, intlLocale(), { sensitivity: 'base' })
  )
  for (const name of [...sortedUsed, ...suggestedNames]) {
    const key = name.toLocaleLowerCase()
    if (seen.has(key) || taken.has(key) || !key.includes(needle)) continue
    seen.add(key)
    options.push(name)
  }

  return options
}

/**
 * The tree as the rows a song's page shows, top to bottom: each folder, then, while it is open, its
 * sub-folders and its files one level in, or a line saying it is empty. The song's root files come last.
 * `featured` is the tab card's file: shown in its folder, left out of the root list it heads already.
 *
 * @param {{folders: Array<SongFolderNode>, files: Array<Object>}} tree from buildSongFileTree()
 * @param {ReadonlySet<string>} openFolderIds
 * @param {Object|null} featured
 * @returns {Array<{type: 'folder'|'file'|'empty', key: string, level: number, node?: SongFolderNode, file?: Object, folderId?: string|null}>}
 *   each row carries the folder a drop on it lands in: the folder itself, or the one a file or an
 *   empty line stands in
 */
export function visibleSongFileRows(tree, openFolderIds, featured = null) {
  const rows = []
  function walk(nodes, level) {
    for (const node of nodes) {
      const folderId = node.folder.id
      rows.push({ type: 'folder', key: `folder:${folderId}`, level, node, folderId })
      if (!openFolderIds.has(folderId)) continue
      walk(node.folders, level + 1)
      for (const file of node.files) {
        rows.push({ type: 'file', key: `file:${file.id}`, level: level + 1, file, folderId })
      }
      if (node.folders.length === 0 && node.files.length === 0) {
        rows.push({ type: 'empty', key: `empty:${folderId}`, level: level + 1, folderId })
      }
    }
  }
  walk(tree.folders, 0)
  for (const file of tree.files) {
    if (file !== featured)
      rows.push({ type: 'file', key: `file:${file.id}`, level: 0, file, folderId: null })
  }

  return rows
}
