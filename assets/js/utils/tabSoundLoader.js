/**
 * Downloads the tab reader's SoundFont files (GeneralUser split per instrument, see vite.config.js):
 * the manifest once, each file once while a reader is open, its bytes counted as they arrive so the
 * player can show how far the sounds are. A failed request is forgotten, so the next call tries again.
 * `fetchFile` is injected so a test can feed it.
 */
export function createSoundFontLoader({ baseUrl, manifestName, fetchFile = (url) => fetch(url) }) {
  let manifestRequest = null
  const fileRequests = new Map()

  function manifest() {
    if (!manifestRequest) {
      manifestRequest = fetchFile(`${baseUrl}${manifestName}`).then((response) => {
        if (!response.ok) throw new Error(`SoundFont manifest: ${response.status}`)
        return response.json()
      })
      manifestRequest.catch(() => {
        manifestRequest = null
      })
    }
    return manifestRequest
  }

  async function read(preset, onBytes) {
    const response = await fetchFile(`${baseUrl}${preset.file}`)
    if (!response.ok || !response.body)
      throw new Error(`SoundFont ${preset.file}: ${response.status}`)
    const chunks = []
    let received = 0
    const reader = response.body.getReader()
    for (;;) {
      const { done, value } = await reader.read()
      if (done) break
      chunks.push(value)
      received += value.length
      onBytes(value.length)
    }
    const bytes = new Uint8Array(received)
    let at = 0
    for (const chunk of chunks) {
      bytes.set(chunk, at)
      at += chunk.length
    }
    return bytes
  }

  function file(preset, onBytes) {
    if (fileRequests.has(preset.file)) {
      onBytes(preset.size)
      return fileRequests.get(preset.file)
    }
    const request = read(preset, onBytes)
    request.catch(() => fileRequests.delete(preset.file))
    fileRequests.set(preset.file, request)
    return request
  }

  /** The bytes of each preset, in order; onProgress gets the share received so far (0 to 1). */
  function download(presets, onProgress = () => {}) {
    const size = presets.reduce((total, preset) => total + preset.size, 0) || 1
    let received = 0
    return Promise.all(
      presets.map((preset) =>
        file(preset, (count) => {
          received += count
          onProgress(Math.min(1, received / size))
        })
      )
    )
  }

  /** Lets the downloaded bytes go, for when no reader is open: the browser cache serves them again. */
  function forget() {
    fileRequests.clear()
  }

  return { manifest, download, forget }
}
