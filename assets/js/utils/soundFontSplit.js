/**
 * Cuts a SoundFont 2 bank into one file per preset (instrument or drum kit), each holding only the
 * instruments and samples that preset plays. Runs at build time (see vite.config.js): the tab reader
 * then downloads a song's few instruments instead of a whole bank. Pure functions on Uint8Array, no
 * Node API, so `node --test` covers them.
 *
 * Format reference: the SoundFont 2.04 specification (RIFF « sfbk » with INFO, sdta and pdta lists).
 * Only 16-bit SF2 is handled: a 24-bit `sm24` chunk is dropped, a compressed SF3 bank is refused.
 */

const RECORD = Object.freeze({
  phdr: 38,
  pbag: 4,
  pmod: 10,
  pgen: 4,
  inst: 22,
  ibag: 4,
  imod: 10,
  igen: 4,
  shdr: 46
})
const GEN_INSTRUMENT = 41
const GEN_SAMPLE_ID = 53
/** The spec wants at least 46 zero sample points after each sample. */
const SAMPLE_PADDING = 46
const STEREO_LINKED = 2 | 4 | 8

const text = (bytes, at, length) => {
  let end = at
  while (end < at + length && bytes[end] !== 0) end++
  return String.fromCharCode(...bytes.subarray(at, end))
}

function* chunks(bytes, view, start, end) {
  for (let at = start; at + 8 <= end; ) {
    const size = view.getUint32(at + 4, true)
    yield { id: text(bytes, at, 4), at, data: at + 8, size }
    at += 8 + size + (size & 1)
  }
}

/** The bank's INFO bytes, its sample data and its pdta records, read without copying. */
export function parseSoundFont(bytes) {
  const view = new DataView(bytes.buffer, bytes.byteOffset, bytes.byteLength)
  if (text(bytes, 0, 4) !== 'RIFF' || text(bytes, 8, 4) !== 'sfbk')
    throw new Error('Not a SoundFont 2 file')
  const font = { info: null, smpl: null, pdta: {} }
  for (const list of chunks(bytes, view, 12, bytes.length)) {
    if (list.id !== 'LIST') continue
    const type = text(bytes, list.data, 4)
    if (type === 'INFO') font.info = bytes.subarray(list.data + 4, list.data + list.size)
    for (const sub of chunks(bytes, view, list.data + 4, list.data + list.size)) {
      if (type === 'INFO' && sub.id === 'ifil' && view.getUint16(sub.data, true) >= 3) {
        throw new Error('Compressed SF3 banks are not supported')
      }
      if (type === 'sdta' && sub.id === 'smpl')
        font.smpl = bytes.subarray(sub.data, sub.data + sub.size)
      if (type === 'pdta' && RECORD[sub.id])
        font.pdta[sub.id] = { view, at: sub.data, count: sub.size / RECORD[sub.id] }
    }
  }
  for (const id of Object.keys(RECORD))
    if (!font.pdta[id]) throw new Error(`SoundFont without a ${id} chunk`)
  if (!font.smpl) throw new Error('SoundFont without sample data')
  return { ...font, bytes }
}

const u16 = (table, index, offset) =>
  table.view.getUint16(table.at + index * RECORD[table.id] + offset, true)
const u32 = (table, index, offset) =>
  table.view.getUint32(table.at + index * RECORD[table.id] + offset, true)
const record = (font, id, index) => {
  const table = font.pdta[id]
  const at = table.at + index * RECORD[id]
  return font.bytes.subarray(at, at + RECORD[id])
}
const table = (font, id) => ({ ...font.pdta[id], id })

/** Every preset of the bank (the terminal record left out): name, bank, program. */
export function listPresets(font) {
  const phdr = table(font, 'phdr')
  const presets = []
  for (let p = 0; p < phdr.count - 1; p++) {
    presets.push({
      index: p,
      name: text(font.bytes, phdr.at + p * RECORD.phdr, 20),
      program: u16(phdr, p, 20),
      bank: u16(phdr, p, 22)
    })
  }
  return presets
}

/** A writer for one pdta table: records go in, the terminal record goes last. */
function tableWriter(size) {
  const parts = []
  return {
    get count() {
      return parts.length
    },
    push(bytes) {
      parts.push(bytes)
    },
    bytes(terminal = new Uint8Array(size)) {
      const out = new Uint8Array((parts.length + 1) * size)
      for (const [k, part] of parts.entries()) out.set(part, k * size)
      out.set(terminal, parts.length * size)
      return out
    }
  }
}

const copyOf = (bytes) => Uint8Array.from(bytes)
const setU16 = (bytes, offset, value) =>
  new DataView(bytes.buffer, bytes.byteOffset).setUint16(offset, value, true)
const setU32 = (bytes, offset, value) =>
  new DataView(bytes.buffer, bytes.byteOffset).setUint32(offset, value, true)

function nameRecord(name, size) {
  const out = new Uint8Array(size)
  for (let k = 0; k < name.length; k++) out[k] = name.charCodeAt(k)
  return out
}

function chunk(id, data) {
  const out = new Uint8Array(8 + data.length + (data.length & 1))
  out.set(nameRecord(id, 4), 0)
  setU32(out, 4, data.length)
  out.set(data, 8)
  return out
}

function list(type, parts) {
  return chunk('LIST', concat([nameRecord(type, 4), ...parts]))
}

function concat(parts) {
  const out = new Uint8Array(parts.reduce((total, part) => total + part.length, 0))
  let at = 0
  for (const part of parts) {
    out.set(part, at)
    at += part.length
  }
  return out
}

/** The zones (bag ranges) of a preset or an instrument, each with its generator and modulator ranges. */
function zones(bags, from, to) {
  const out = []
  for (let b = from; b < to; b++)
    out.push({
      gens: [u16(bags, b, 0), u16(bags, b + 1, 0)],
      mods: [u16(bags, b, 2), u16(bags, b + 1, 2)]
    })
  return out
}

/** The instruments a preset plays and the samples those play, stereo partners included. */
function presetContents(font, presetIndex) {
  const phdr = table(font, 'phdr')
  const pgen = table(font, 'pgen')
  const inst = table(font, 'inst')
  const igen = table(font, 'igen')
  const shdr = table(font, 'shdr')
  const presetZones = zones(
    table(font, 'pbag'),
    u16(phdr, presetIndex, 24),
    u16(phdr, presetIndex + 1, 24)
  )
  const instruments = []
  for (const zone of presetZones) {
    for (let g = zone.gens[0]; g < zone.gens[1]; g++) {
      if (u16(pgen, g, 0) === GEN_INSTRUMENT && !instruments.includes(u16(pgen, g, 2)))
        instruments.push(u16(pgen, g, 2))
    }
  }
  const instrumentZones = new Map()
  const samples = []
  const addSample = (s) => {
    if (samples.includes(s)) return
    samples.push(s)
    if (u16(shdr, s, 44) & STEREO_LINKED) addSample(u16(shdr, s, 42))
  }
  for (const i of instruments) {
    const list = zones(table(font, 'ibag'), u16(inst, i, 20), u16(inst, i + 1, 20))
    instrumentZones.set(i, list)
    for (const zone of list) {
      for (let g = zone.gens[0]; g < zone.gens[1]; g++)
        if (u16(igen, g, 0) === GEN_SAMPLE_ID) addSample(u16(igen, g, 2))
    }
  }
  return { presetZones, instruments, instrumentZones, samples }
}

/** One complete SF2 holding a single preset of the bank. */
function presetFile(font, preset) {
  const { presetZones, instruments, instrumentZones, samples } = presetContents(font, preset.index)
  const instrumentIndex = new Map(instruments.map((old, k) => [old, k]))
  const sampleIndex = new Map(samples.map((old, k) => [old, k]))
  const shdrTable = table(font, 'shdr')

  // The bags, generators and modulators of a list of zones, with the one generator that points at
  // another table (instrument or sample) renumbered for the new file.
  const zoneTables = (zoneList, genId, modId, pointer, renumber) => {
    const genTable = table(font, genId)
    const bags = tableWriter(RECORD.pbag)
    const gens = tableWriter(RECORD.pgen)
    const mods = tableWriter(RECORD.pmod)
    for (const zone of zoneList) {
      const bag = new Uint8Array(4)
      setU16(bag, 0, gens.count)
      setU16(bag, 2, mods.count)
      bags.push(bag)
      for (let g = zone.gens[0]; g < zone.gens[1]; g++) {
        const gen = copyOf(record(font, genId, g))
        if (u16(genTable, g, 0) === pointer) setU16(gen, 2, renumber.get(u16(genTable, g, 2)))
        gens.push(gen)
      }
      for (let m = zone.mods[0]; m < zone.mods[1]; m++) mods.push(copyOf(record(font, modId, m)))
    }
    return { bags, gens, mods }
  }

  const presetTables = zoneTables(presetZones, 'pgen', 'pmod', GEN_INSTRUMENT, instrumentIndex)
  const instrumentList = instruments.flatMap((i) => instrumentZones.get(i))
  const instrumentTables = zoneTables(instrumentList, 'igen', 'imod', GEN_SAMPLE_ID, sampleIndex)

  const phdr = tableWriter(RECORD.phdr)
  const head = copyOf(record(font, 'phdr', preset.index))
  setU16(head, 24, 0)
  phdr.push(head)
  const eop = nameRecord('EOP', RECORD.phdr)
  setU16(eop, 24, presetTables.bags.count)

  const inst = tableWriter(RECORD.inst)
  let bagCursor = 0
  for (const i of instruments) {
    const entry = copyOf(record(font, 'inst', i))
    setU16(entry, 20, bagCursor)
    bagCursor += instrumentZones.get(i).length
    inst.push(entry)
  }
  const eoi = nameRecord('EOI', RECORD.inst)
  setU16(eoi, 20, bagCursor)

  const shdr = tableWriter(RECORD.shdr)
  const sampleData = []
  let cursor = 0
  for (const s of samples) {
    const start = u32(shdrTable, s, 20)
    const end = u32(shdrTable, s, 24)
    const shift = cursor - start
    sampleData.push(font.smpl.subarray(start * 2, end * 2), new Uint8Array(SAMPLE_PADDING * 2))
    const header = copyOf(record(font, 'shdr', s))
    setU32(header, 20, start + shift)
    setU32(header, 24, end + shift)
    setU32(header, 28, u32(shdrTable, s, 28) + shift)
    setU32(header, 32, u32(shdrTable, s, 32) + shift)
    if (u16(shdrTable, s, 44) & STEREO_LINKED)
      setU16(header, 42, sampleIndex.get(u16(shdrTable, s, 42)))
    shdr.push(header)
    cursor += end - start + SAMPLE_PADDING
  }

  const terminalBag = (tables) => {
    const bag = new Uint8Array(4)
    setU16(bag, 0, tables.gens.count)
    setU16(bag, 2, tables.mods.count)
    return bag
  }

  return concat([
    nameRecord('RIFF', 4),
    new Uint8Array(4),
    nameRecord('sfbk', 4),
    list('INFO', [font.info]),
    list('sdta', [chunk('smpl', concat(sampleData))]),
    list('pdta', [
      chunk('phdr', phdr.bytes(eop)),
      chunk('pbag', presetTables.bags.bytes(terminalBag(presetTables))),
      chunk('pmod', presetTables.mods.bytes()),
      chunk('pgen', presetTables.gens.bytes()),
      chunk('inst', inst.bytes(eoi)),
      chunk('ibag', instrumentTables.bags.bytes(terminalBag(instrumentTables))),
      chunk('imod', instrumentTables.mods.bytes()),
      chunk('igen', instrumentTables.gens.bytes()),
      chunk('shdr', shdr.bytes(nameRecord('EOS', RECORD.shdr)))
    ])
  ])
}

/**
 * One SF2 per preset the `keep(bank, program)` filter accepts, with the RIFF size filled in.
 * Returns `{ bank, program, name, bytes }` in bank order.
 */
export function splitSoundFont(bytes, { keep = () => true } = {}) {
  const font = parseSoundFont(bytes)
  return listPresets(font)
    .filter((preset) => keep(preset.bank, preset.program))
    .map((preset) => {
      const file = presetFile(font, preset)
      setU32(file, 4, file.length - 8)
      return { bank: preset.bank, program: preset.program, name: preset.name, bytes: file }
    })
}
