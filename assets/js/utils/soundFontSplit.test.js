import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { listPresets, parseSoundFont, splitSoundFont } from './soundFontSplit.js'

// A tiny SoundFont 2, written field by field from the 2.04 specification.

const GEN = Object.freeze({ PAN: 17, KEY_RANGE: 43, INSTRUMENT: 41, SAMPLE_ID: 53 })
const MONO = 1
const RIGHT = 2
const LEFT = 4

function bytesOf(size, fill) {
  const bytes = new Uint8Array(size)
  fill(new DataView(bytes.buffer))
  return bytes
}
const name = (view, text, at = 0) => {
  for (let k = 0; k < text.length; k++) view.setUint8(at + k, text.charCodeAt(k))
}
const join = (parts) => {
  const out = new Uint8Array(parts.reduce((total, part) => total + part.length, 0))
  let at = 0
  for (const part of parts) {
    out.set(part, at)
    at += part.length
  }
  return out
}
const chunk = (id, data) =>
  join([
    bytesOf(8, (v) => {
      name(v, id)
      v.setUint32(4, data.length, true)
    }),
    data,
    new Uint8Array(data.length & 1)
  ])
const list = (type, parts) => chunk('LIST', join([bytesOf(4, (v) => name(v, type)), ...parts]))

/**
 * presets: [{ name, bank, program, zones: [{ gens: [[oper, amount]], mods: number }] }]
 * instruments: [{ name, zones: [{ gens: [[oper, amount]] }] }]
 * samples: [{ name, points: number[], type, link, loop: [start, end] }] (loop relative to the sample)
 */
function soundFont({ presets, instruments, samples, ifilMajor = 2 }) {
  const records = (zoneLists, genSize) => {
    const bags = []
    const gens = []
    const mods = []
    for (const zones of zoneLists) {
      for (const zone of zones) {
        bags.push([gens.length, mods.length])
        for (const gen of zone.gens) gens.push(gen)
        for (let m = 0; m < (zone.mods ?? 0); m++) mods.push(m + 1)
      }
    }
    return {
      bag: join(
        [...bags, [gens.length, mods.length]].map(([g, m]) =>
          bytesOf(4, (v) => (v.setUint16(0, g, true), v.setUint16(2, m, true)))
        )
      ),
      gen: join(
        [...gens, [0, 0]].map(([oper, amount]) =>
          bytesOf(genSize, (v) => (v.setUint16(0, oper, true), v.setUint16(2, amount, true)))
        )
      ),
      mod: join([...mods, 0].map((marker) => bytesOf(10, (v) => v.setUint16(0, marker, true))))
    }
  }
  const p = records(
    presets.map((preset) => preset.zones),
    4
  )
  const i = records(
    instruments.map((instrument) => instrument.zones),
    4
  )

  let presetBag = 0
  const phdr = join([
    ...presets.map((preset) =>
      bytesOf(38, (v) => {
        name(v, preset.name)
        v.setUint16(20, preset.program, true)
        v.setUint16(22, preset.bank, true)
        v.setUint16(24, presetBag, true)
        presetBag += preset.zones.length
      })
    ),
    bytesOf(38, (v) => (name(v, 'EOP'), v.setUint16(24, presetBag, true)))
  ])
  let instrumentBag = 0
  const inst = join([
    ...instruments.map((instrument) =>
      bytesOf(22, (v) => {
        name(v, instrument.name)
        v.setUint16(20, instrumentBag, true)
        instrumentBag += instrument.zones.length
      })
    ),
    bytesOf(22, (v) => (name(v, 'EOI'), v.setUint16(20, instrumentBag, true)))
  ])

  const smplParts = []
  let cursor = 0
  const shdr = join([
    ...samples.map((sample) => {
      const start = cursor
      smplParts.push(
        bytesOf(sample.points.length * 2, (v) => {
          for (const [k, point] of sample.points.entries()) v.setInt16(k * 2, point, true)
        })
      )
      smplParts.push(new Uint8Array(46 * 2))
      cursor += sample.points.length + 46
      return bytesOf(46, (v) => {
        name(v, sample.name)
        v.setUint32(20, start, true)
        v.setUint32(24, start + sample.points.length, true)
        v.setUint32(28, start + sample.loop[0], true)
        v.setUint32(32, start + sample.loop[1], true)
        v.setUint32(36, 44100, true)
        v.setUint8(40, 60)
        v.setUint16(42, sample.link ?? 0, true)
        v.setUint16(44, sample.type, true)
      })
    }),
    bytesOf(46, (v) => name(v, 'EOS'))
  ])

  const body = join([
    bytesOf(4, (v) => name(v, 'sfbk')),
    list('INFO', [
      chunk(
        'ifil',
        bytesOf(4, (v) => (v.setUint16(0, ifilMajor, true), v.setUint16(2, 1, true)))
      ),
      chunk(
        'INAM',
        bytesOf(10, (v) => name(v, 'Test bank'))
      )
    ]),
    list('sdta', [chunk('smpl', join(smplParts))]),
    list('pdta', [
      chunk('phdr', phdr),
      chunk('pbag', p.bag),
      chunk('pmod', p.mod),
      chunk('pgen', p.gen),
      chunk('inst', inst),
      chunk('ibag', i.bag),
      chunk('imod', i.mod),
      chunk('igen', i.gen),
      chunk('shdr', shdr)
    ])
  ])
  return join([bytesOf(8, (v) => (name(v, 'RIFF'), v.setUint32(4, body.length, true))), body])
}

const BANK = soundFont({
  samples: [
    { name: 'Pluck', points: [1, 2, 3, 4, 5, 6], type: MONO, loop: [1, 5] },
    { name: 'PadL', points: [10, 11, 12, 13], type: LEFT, link: 2, loop: [0, 3] },
    { name: 'PadR', points: [20, 21, 22, 23], type: RIGHT, link: 1, loop: [0, 3] },
    { name: 'Unused', points: [99, 99, 99], type: MONO, loop: [0, 2] }
  ],
  instruments: [
    {
      name: 'Guitar',
      zones: [
        { gens: [[GEN.PAN, 5]] },
        {
          gens: [
            [GEN.KEY_RANGE, 0x7f00],
            [GEN.SAMPLE_ID, 0]
          ]
        }
      ]
    },
    { name: 'Pad', zones: [{ gens: [[GEN.SAMPLE_ID, 1]] }, { gens: [[GEN.SAMPLE_ID, 2]] }] }
  ],
  presets: [
    { name: 'Guitar', bank: 0, program: 30, zones: [{ gens: [[GEN.INSTRUMENT, 0]], mods: 2 }] },
    { name: 'Pad', bank: 0, program: 89, zones: [{ gens: [[GEN.INSTRUMENT, 1]] }] },
    {
      name: 'Kit',
      bank: 128,
      program: 0,
      zones: [
        { gens: [[GEN.PAN, 1]] },
        { gens: [[GEN.INSTRUMENT, 0]] },
        { gens: [[GEN.INSTRUMENT, 1]] }
      ]
    }
  ]
})

/** Reads a split file back: its presets, instruments, samples and what each points at. */
function readBack(bytes) {
  const font = parseSoundFont(bytes)
  const at = (id, index, offset) =>
    font.pdta[id].view.getUint16(
      font.pdta[id].at +
        index * { pbag: 4, pgen: 4, inst: 22, ibag: 4, igen: 4, shdr: 46 }[id] +
        offset,
      true
    )
  const u32 = (index, offset) =>
    font.pdta.shdr.view.getUint32(font.pdta.shdr.at + index * 46 + offset, true)
  const nameAt = (id, index, size) => {
    const start = font.pdta[id].at + index * size
    return String.fromCharCode(...bytes.subarray(start, start + 20)).replace(/\0.*$/s, '')
  }
  const gensOf = (bagId, genId, from, to) => {
    const out = []
    for (let b = from; b < to; b++) {
      const gens = []
      for (let g = at(bagId, b, 0); g < at(bagId, b + 1, 0); g++)
        gens.push([at(genId, g, 0), at(genId, g, 2)])
      out.push(gens)
    }
    return out
  }
  const instruments = []
  for (let k = 0; k < font.pdta.inst.count - 1; k++) {
    instruments.push({
      name: nameAt('inst', k, 22),
      zones: gensOf('ibag', 'igen', at('inst', k, 20), at('inst', k + 1, 20))
    })
  }
  const samples = []
  for (let k = 0; k < font.pdta.shdr.count - 1; k++) {
    const start = u32(k, 20)
    const end = u32(k, 24)
    const points = []
    for (let n = start; n < end; n++)
      points.push(new DataView(font.smpl.buffer, font.smpl.byteOffset).getInt16(n * 2, true))
    samples.push({
      name: nameAt('shdr', k, 46),
      start,
      end,
      loop: [u32(k, 28), u32(k, 32)],
      link: at('shdr', k, 42),
      points
    })
  }
  const presetBag = (k) => font.pdta.phdr.view.getUint16(font.pdta.phdr.at + k * 38 + 24, true)
  return {
    font,
    presets: listPresets(font),
    presetZones: gensOf('pbag', 'pgen', presetBag(0), presetBag(1)),
    instruments,
    samples,
    terminals: {
      eopBag: presetBag(1),
      pbags: font.pdta.pbag.count - 1,
      eoiBag: at('inst', font.pdta.inst.count - 1, 20),
      ibags: font.pdta.ibag.count - 1
    }
  }
}

describe('splitSoundFont', () => {
  it('writes one file per preset, in bank order, named by the preset', () => {
    const files = splitSoundFont(BANK)

    assert.deepEqual(
      files.map(({ bank, program, name }) => ({ bank, program, name })),
      [
        { bank: 0, program: 30, name: 'Guitar' },
        { bank: 0, program: 89, name: 'Pad' },
        { bank: 128, program: 0, name: 'Kit' }
      ]
    )
  })

  it('keeps only the presets the filter accepts', () => {
    const files = splitSoundFont(BANK, { keep: (bank) => bank === 128 })

    assert.deepEqual(
      files.map((file) => file.name),
      ['Kit']
    )
  })

  it('gives a file its preset, its instrument and only the samples it plays', () => {
    const pad = readBack(splitSoundFont(BANK)[1].bytes)

    assert.deepEqual(pad.presets, [{ index: 0, name: 'Pad', program: 89, bank: 0 }])
    assert.deepEqual(pad.presetZones, [[[GEN.INSTRUMENT, 0]]])
    assert.deepEqual(pad.instruments, [
      { name: 'Pad', zones: [[[GEN.SAMPLE_ID, 0]], [[GEN.SAMPLE_ID, 1]]] }
    ])
    assert.deepEqual(
      pad.samples.map((sample) => sample.name),
      ['PadL', 'PadR']
    )
  })

  it('copies the sample points, moves the loops with them and pads each sample', () => {
    const { samples } = readBack(splitSoundFont(BANK)[1].bytes)

    assert.deepEqual(samples[0], {
      name: 'PadL',
      start: 0,
      end: 4,
      loop: [0, 3],
      link: 1,
      points: [10, 11, 12, 13]
    })
    assert.deepEqual(samples[1], {
      name: 'PadR',
      start: 50,
      end: 54,
      loop: [50, 53],
      link: 0,
      points: [20, 21, 22, 23]
    })
  })

  it('renumbers a preset playing several instruments, global zones kept', () => {
    const kit = readBack(splitSoundFont(BANK)[2].bytes)

    assert.deepEqual(kit.presetZones, [
      [[GEN.PAN, 1]],
      [[GEN.INSTRUMENT, 0]],
      [[GEN.INSTRUMENT, 1]]
    ])
    assert.deepEqual(kit.instruments, [
      {
        name: 'Guitar',
        zones: [
          [[GEN.PAN, 5]],
          [
            [GEN.KEY_RANGE, 0x7f00],
            [GEN.SAMPLE_ID, 0]
          ]
        ]
      },
      { name: 'Pad', zones: [[[GEN.SAMPLE_ID, 1]], [[GEN.SAMPLE_ID, 2]]] }
    ])
    assert.deepEqual(
      kit.samples.map(({ name, link }) => ({ name, link })),
      [
        { name: 'Pluck', link: 0 },
        { name: 'PadL', link: 2 },
        { name: 'PadR', link: 1 }
      ]
    )
    assert.deepEqual(kit.samples[0].loop, [1, 5])
  })

  it('keeps the zone modulators and closes every table with its terminal record', () => {
    const guitar = readBack(splitSoundFont(BANK)[0].bytes)

    assert.equal(guitar.font.pdta.pmod.count, 3)
    assert.deepEqual(guitar.terminals, { eopBag: 1, pbags: 1, eoiBag: 2, ibags: 2 })
  })

  it('writes a valid RIFF size, the bank INFO, and a smaller file than the bank', () => {
    const { bytes } = splitSoundFont(BANK)[0]
    const view = new DataView(bytes.buffer, bytes.byteOffset)

    assert.equal(view.getUint32(4, true), bytes.length - 8)
    assert.equal(String.fromCharCode(...bytes.subarray(0, 4)), 'RIFF')
    assert.ok(String.fromCharCode(...parseSoundFont(bytes).info).includes('Test bank'))
    assert.ok(bytes.length < BANK.length)
  })

  it('refuses a file that is not a SoundFont 2, and a compressed SF3', () => {
    assert.throws(() => splitSoundFont(new Uint8Array(64)), /Not a SoundFont 2 file/)
    assert.throws(
      () => splitSoundFont(soundFont({ samples: [], instruments: [], presets: [], ifilMajor: 3 })),
      /SF3/
    )
  })
})
