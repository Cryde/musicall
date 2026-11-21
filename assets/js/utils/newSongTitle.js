/**
 * The title a new song takes when none was typed (#1431): its first file's name without the extension.
 * A composer exports « New 46.gp » and drops it in: the song is « New 46 ».
 *
 * @param {ReadonlyArray<{name: string}>} files in the order they were picked
 * @returns {string} empty without a file
 */
export function titleFromFirstFile(files) {
  if (files.length === 0) return ''
  const name = files[0].name
  const dot = name.lastIndexOf('.')
  return (dot > 0 ? name.slice(0, dot) : name).trim()
}
