import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { createSoundFontLoader } from './tabSoundLoader.js'

const MANIFEST = {
  presets: [
    { bank: 0, program: 30, file: '0-30.sf2', size: 6 },
    { bank: 128, program: 0, file: '128-0.sf2', size: 4 }
  ]
}
const BYTES = { '0-30.sf2': [1, 2, 3, 4, 5, 6], '128-0.sf2': [7, 8, 9, 10] }

/** A fetch answering from memory, sending each file in two chunks, counting the calls per URL. */
function fakeFetch({ failing = new Set() } = {}) {
  const calls = {}
  const fetchFile = async (url) => {
    const name = url.split('/').pop()
    calls[name] = (calls[name] ?? 0) + 1
    if (failing.has(name)) return new Response('', { status: 503 })
    if (name === 'manifest.json') return Response.json(MANIFEST)
    const data = Uint8Array.from(BYTES[name])
    const half = Math.ceil(data.length / 2)
    const stream = new ReadableStream({
      start(controller) {
        controller.enqueue(data.subarray(0, half))
        controller.enqueue(data.subarray(half))
        controller.close()
      }
    })
    return new Response(stream)
  }
  return { fetchFile, calls, failing }
}

const loaderWith = (fake) =>
  createSoundFontLoader({
    baseUrl: '/alphatab/sf/',
    manifestName: 'manifest.json',
    fetchFile: fake.fetchFile
  })

describe('createSoundFontLoader', () => {
  it('reads the manifest once', async () => {
    const fake = fakeFetch()
    const loader = loaderWith(fake)

    assert.deepEqual(await loader.manifest(), MANIFEST)
    await loader.manifest()
    assert.equal(fake.calls['manifest.json'], 1)
  })

  it('downloads each file whole, in the order asked, and reports progress up to 1', async () => {
    const loader = loaderWith(fakeFetch())
    const progress = []

    const files = await loader.download(MANIFEST.presets, (ratio) => progress.push(ratio))

    assert.deepEqual(
      files.map((bytes) => [...bytes]),
      [BYTES['0-30.sf2'], BYTES['128-0.sf2']]
    )
    assert.equal(progress.at(-1), 1)
    assert.ok(progress.every((ratio, k) => k === 0 || ratio >= progress[k - 1]))
  })

  it('downloads a file once, and counts it as received straight away the next time', async () => {
    const fake = fakeFetch()
    const loader = loaderWith(fake)
    await loader.download([MANIFEST.presets[0]])
    const progress = []

    await loader.download([MANIFEST.presets[0]], (ratio) => progress.push(ratio))

    assert.equal(fake.calls['0-30.sf2'], 1)
    assert.deepEqual(progress, [1])
  })

  it('tries a failed file again on the next download', async () => {
    const fake = fakeFetch({ failing: new Set(['128-0.sf2']) })
    const loader = loaderWith(fake)

    await assert.rejects(loader.download(MANIFEST.presets), /128-0\.sf2: 503/)
    fake.failing.clear()
    const files = await loader.download(MANIFEST.presets)

    assert.deepEqual([...files[1]], BYTES['128-0.sf2'])
    assert.equal(fake.calls['128-0.sf2'], 2)
    assert.equal(fake.calls['0-30.sf2'], 1)
  })

  it('downloads a file again once forgotten', async () => {
    const fake = fakeFetch()
    const loader = loaderWith(fake)
    await loader.download([MANIFEST.presets[0]])

    loader.forget()
    await loader.download([MANIFEST.presets[0]])

    assert.equal(fake.calls['0-30.sf2'], 2)
  })

  it('tries a failed manifest again on the next call', async () => {
    const fake = fakeFetch({ failing: new Set(['manifest.json']) })
    const loader = loaderWith(fake)

    await assert.rejects(loader.manifest(), /manifest: 503/)
    fake.failing.clear()

    assert.deepEqual(await loader.manifest(), MANIFEST)
  })
})
