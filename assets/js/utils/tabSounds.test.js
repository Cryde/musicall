import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { presetForTrack, soundFontFilesFor } from './tabSounds.js'

const preset = (bank, program, size) => ({
  bank,
  program,
  name: `${bank}:${program}`,
  file: `${bank}-${program}.sf2`,
  size
})
const PRESETS = [
  preset(0, 29, 890),
  preset(0, 30, 830),
  preset(0, 34, 150),
  preset(128, 0, 2820),
  preset(128, 16, 3090)
]

describe('presetForTrack', () => {
  it('plays a melodic track from its program in the main bank', () => {
    assert.equal(presetForTrack({ program: 30, isPercussion: false }, PRESETS).file, '0-30.sf2')
  })

  it('plays a drum track from its kit, the standard kit when the file asks for one that is missing', () => {
    assert.equal(presetForTrack({ program: 16, isPercussion: true }, PRESETS).file, '128-16.sf2')
    assert.equal(presetForTrack({ program: 25, isPercussion: true }, PRESETS).file, '128-0.sf2')
  })

  it('answers null when nothing in the manifest fits', () => {
    assert.equal(presetForTrack({ program: 73, isPercussion: false }, PRESETS), null)
    assert.equal(presetForTrack({ program: 0, isPercussion: true }, []), null)
  })
})

describe('soundFontFilesFor', () => {
  it('downloads each instrument once, in manifest order', () => {
    const tracks = [
      { program: 30, isPercussion: false },
      { program: 29, isPercussion: false },
      { program: 30, isPercussion: false },
      { program: 34, isPercussion: false },
      { program: 0, isPercussion: true }
    ]

    assert.deepEqual(
      soundFontFilesFor(tracks, PRESETS).map((file) => file.file),
      ['0-29.sf2', '0-30.sf2', '0-34.sf2', '128-0.sf2']
    )
  })

  it('leaves out a track nothing fits, and downloads nothing for no track', () => {
    assert.deepEqual(soundFontFilesFor([{ program: 73, isPercussion: false }], PRESETS), [])
    assert.deepEqual(soundFontFilesFor([], PRESETS), [])
  })
})
