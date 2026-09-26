/**
 * Song lyrics in ChordPro (#1055): chords inline as `[Am]`, verse, chorus and bridge sections, and
 * `{comment}`. Who sings a passage is our own extension, `<span singer="@[userId] @[userId]">…</span>`
 * or `singer="all"`, one span per line, so a line stays valid ChordPro markup.
 *
 * App\Service\BandSpace\Song\ChordPro\ChordProParser is the PHP twin, used by the PDF. Both run
 * against tests/Fixtures/ChordPro/cases.json so they cannot drift.
 */

export const SINGER_ALL = 'all'

/**
 * The parts a song is cut into, in the order they usually come, with the name the band reads.
 * ChordPro 6 allows any `start_of_<name>`; these are the ones this app draws as a part. Another name
 * (`start_of_tab`) stays a directive kept verbatim. Mirrors ChordProParser::SECTION_KINDS.
 */
export const SECTION_KINDS = [
  { value: 'intro', label: 'Intro' },
  { value: 'verse', label: 'Couplet' },
  { value: 'prechorus', label: 'Pré-refrain' },
  { value: 'chorus', label: 'Refrain' },
  { value: 'bridge', label: 'Pont' },
  { value: 'break', label: 'Break' },
  { value: 'solo', label: 'Solo' },
  { value: 'interlude', label: 'Interlude' },
  { value: 'outro', label: 'Outro' }
]
const KIND_VALUES = SECTION_KINDS.map((kind) => kind.value)
const KIND_ALIASES = { v: 'verse', c: 'chorus', b: 'bridge', pre_chorus: 'prechorus' }
const SECTION_DIRECTIVE = /^(?:(start|end)_of_([a-z_]+)|(so|eo)([vcb]))$/

/** `start_of_solo` is { edge: 'start', kind: 'solo' }; `eoc` is { edge: 'end', kind: 'chorus' }; else null. */
function sectionDirective(name) {
  const match = name.match(SECTION_DIRECTIVE)
  if (!match) return null
  const edge = match[1] ?? (match[3] === 'so' ? 'start' : 'end')
  const raw = match[2] ?? match[4]
  const kind = KIND_ALIASES[raw] ?? raw
  return KIND_VALUES.includes(kind) ? { edge, kind } : null
}

export function sectionLabel(section) {
  return section.label || (SECTION_KINDS.find((kind) => kind.value === section.kind)?.label ?? '')
}

const COMMENTS = ['comment', 'c']
const DIRECTIVE = /^\{\s*([a-z_]+)\s*(?::\s*(.*?))?\s*\}$/i
const SPAN_OPEN = /^<span singer="([^"]*)">/
const SPAN_CLOSE = '</span>'
const SINGER_ID = /@\[([^\]]+)\]/g

function parseSingers(attribute) {
  if (attribute.trim() === SINGER_ALL) {
    return [SINGER_ALL]
  }
  return [...new Set([...attribute.matchAll(SINGER_ID)].map((match) => match[1]))]
}

/**
 * A lyric line as plain text plus what sits on it: chords at a position, singer ranges over
 * [start, end). Positions count UTF-16 code units, which is only ever compared within this module.
 */
export function parseLine(raw) {
  let text = ''
  const chords = []
  const ranges = []
  let open = null
  let index = 0

  while (index < raw.length) {
    if (raw[index] === '[') {
      const close = raw.indexOf(']', index)
      const name = close > index ? raw.slice(index + 1, close).trim() : ''
      if (name !== '') {
        chords.push({ pos: text.length, name })
        index = close + 1
        continue
      }
    }
    if (raw[index] === '<') {
      const opening = raw.slice(index).match(SPAN_OPEN)
      if (opening) {
        if (open) {
          ranges.push({ ...open, end: text.length })
        }
        const singers = parseSingers(opening[1])
        open = singers.length > 0 ? { start: text.length, singers } : null
        index += opening[0].length
        continue
      }
      if (raw.startsWith(SPAN_CLOSE, index)) {
        if (open) {
          ranges.push({ ...open, end: text.length })
          open = null
        }
        index += SPAN_CLOSE.length
        continue
      }
    }
    text += raw[index]
    index++
  }
  if (open) {
    ranges.push({ ...open, end: text.length })
  }

  return { type: 'line', text, chords, ranges: ranges.filter((range) => range.end > range.start) }
}

/**
 * The song as blocks: a line, a comment, a directive this app does not act on (kept verbatim, so a
 * pasted `{title}` survives an edit), or a section holding any of the three. Sections do not nest:
 * a start inside an open section closes it first, and an unclosed one ends with the song.
 */
export function parse(source) {
  const blocks = []
  let section = null

  for (const raw of (source ?? '').replace(/\r\n?/g, '\n').split('\n')) {
    const directive = raw.trim().match(DIRECTIVE)
    if (!directive) {
      ;(section?.content ?? blocks).push(parseLine(raw))
      continue
    }
    const name = directive[1].toLowerCase()
    const value = directive[2] ?? ''
    const edge = sectionDirective(name)
    if (edge?.edge === 'start') {
      section = { type: 'section', kind: edge.kind, label: value, content: [] }
      blocks.push(section)
    } else if (edge?.edge === 'end') {
      section = null
    } else if (COMMENTS.includes(name)) {
      ;(section?.content ?? blocks).push({ type: 'comment', text: value })
    } else {
      ;(section?.content ?? blocks).push({ type: 'directive', raw: raw.trim() })
    }
  }

  return blocks
}

function singerAttribute(singers) {
  return singers.includes(SINGER_ALL) ? SINGER_ALL : singers.map((id) => `@[${id}]`).join(' ')
}

export function serializeLine(line) {
  // At one position a range closes before the next opens, and a chord goes inside the range it
  // starts, so `<span …>[G]other` rather than `[G]<span …>other`.
  const events = []
  for (const range of line.ranges) {
    events.push({
      pos: range.start,
      order: 1,
      value: `<span singer="${singerAttribute(range.singers)}">`
    })
    events.push({ pos: range.end, order: 0, value: SPAN_CLOSE })
  }
  for (const chord of line.chords) {
    events.push({ pos: chord.pos, order: 2, value: `[${chord.name}]` })
  }
  events.sort((a, b) => a.pos - b.pos || a.order - b.order)

  let out = ''
  let cursor = 0
  for (const event of events) {
    out += line.text.slice(cursor, event.pos) + event.value
    cursor = event.pos
  }
  return out + line.text.slice(cursor)
}

function serializeBlock(block) {
  if (block.type === 'line') return serializeLine(block)
  if (block.type === 'comment') return `{comment: ${block.text}}`
  return block.raw
}

export function serialize(blocks) {
  const lines = []
  for (const block of blocks) {
    if (block.type !== 'section') {
      lines.push(serializeBlock(block))
      continue
    }
    lines.push(
      block.label ? `{start_of_${block.kind}: ${block.label}}` : `{start_of_${block.kind}}`
    )
    lines.push(...block.content.map(serializeBlock))
    lines.push(`{end_of_${block.kind}}`)
  }
  return lines.join('\n')
}

function eachLine(blocks, callback) {
  for (const block of blocks) {
    if (block.type === 'line') callback(block)
    if (block.type !== 'section') continue
    for (const child of block.content) {
      if (child.type === 'line') callback(child)
    }
  }
}

/** Every singer id in order of first appearance, `all` included: that order is what picks colours. */
export function singerIds(blocks) {
  const ids = new Set()
  eachLine(blocks, (line) => {
    for (const range of line.ranges) {
      for (const id of range.singers) ids.add(id)
    }
  })
  return [...ids]
}

/**
 * A line cut for display: one chunk per chord, the chord over the text up to the next one, the text
 * split again wherever the singers change. A chord past the last character still gets its chunk.
 */
export function lineChunks(line) {
  const cuts = [...new Set([0, ...line.chords.map((chord) => chord.pos), line.text.length])].sort(
    (a, b) => a - b
  )
  const chunks = []

  for (let index = 0; index < cuts.length; index++) {
    const from = cuts[index]
    const to = cuts[index + 1] ?? from
    const chords = line.chords.filter((chord) => chord.pos === from).map((chord) => chord.name)
    if (from === to && chords.length === 0 && from === line.text.length && chunks.length > 0) {
      break
    }
    chunks.push({ chords, segments: segmentsBetween(line, from, to) })
  }

  return chunks
}

function segmentsBetween(line, from, to) {
  const bounds = new Set([from, to])
  for (const range of line.ranges) {
    if (range.start > from && range.start < to) bounds.add(range.start)
    if (range.end > from && range.end < to) bounds.add(range.end)
  }
  const sorted = [...bounds].sort((a, b) => a - b)
  const segments = []
  for (let index = 0; index < sorted.length - 1; index++) {
    const start = sorted[index]
    const range = line.ranges.find((candidate) => start >= candidate.start && start < candidate.end)
    segments.push({
      text: line.text.slice(start, sorted[index + 1]),
      singers: range ? range.singers : null
    })
  }
  return segments
}

function sheetBlock(block) {
  if (block.type === 'line')
    return { type: 'line', chunks: lineChunks(block), singerSets: lineSingerSets(block) }
  if (block.type === 'section') return { ...block, content: block.content.map(sheetBlock) }
  return block
}

/** What a reader sees: the blocks with every line cut into chunks. The PDF builds the same shape. */
export function toSheet(blocks) {
  return blocks.map(sheetBlock)
}

/** The singers starting on this line, each set once, in order: what the margin shows. */
export function lineSingerSets(line) {
  const seen = new Set()
  const sets = []
  for (const range of [...line.ranges].sort((a, b) => a.start - b.start)) {
    const key = range.singers.join(' ')
    if (!seen.has(key)) {
      seen.add(key)
      sets.push(range.singers)
    }
  }
  return sets
}

/**
 * Gives a line's [start, end) to these singers, or to nobody with an empty list. What was there
 * before is cut around it, and neighbours sung by the same people are joined back into one range.
 */
export function assignRange(line, start, end, singers) {
  if (end <= start) return line
  const kept = []
  for (const range of line.ranges) {
    if (range.end <= start || range.start >= end) {
      kept.push(range)
      continue
    }
    if (range.start < start) kept.push({ ...range, end: start })
    if (range.end > end) kept.push({ ...range, start: end })
  }
  if (singers.length > 0) kept.push({ start, end, singers })
  kept.sort((a, b) => a.start - b.start)

  const merged = []
  for (const range of kept) {
    const last = merged[merged.length - 1]
    if (last && last.end === range.start && last.singers.join(' ') === range.singers.join(' ')) {
      last.end = range.end
    } else {
      merged.push({ ...range })
    }
  }
  return { ...line, ranges: merged }
}

// ---------------------------------------------------------------------------------------------
// Chords and transposition. English (C, F#m7, Bb/D) and French (Do, Fa#m7, Sib/Ré) notation, each
// chord keeping the notation it was written in.

const ENGLISH_SHARPS = ['C', 'C#', 'D', 'D#', 'E', 'F', 'F#', 'G', 'G#', 'A', 'A#', 'B']
const ENGLISH_FLATS = ['C', 'Db', 'D', 'Eb', 'E', 'F', 'Gb', 'G', 'Ab', 'A', 'Bb', 'B']
const LATIN_SHARPS = ['Do', 'Do#', 'Ré', 'Ré#', 'Mi', 'Fa', 'Fa#', 'Sol', 'Sol#', 'La', 'La#', 'Si']
const LATIN_FLATS = ['Do', 'Réb', 'Ré', 'Mib', 'Mi', 'Fa', 'Solb', 'Sol', 'Lab', 'La', 'Sib', 'Si']
const NATURALS = {
  C: 0,
  D: 2,
  E: 4,
  F: 5,
  G: 7,
  A: 9,
  B: 11,
  Do: 0,
  Ré: 2,
  Re: 2,
  Mi: 4,
  Fa: 5,
  Sol: 7,
  La: 9,
  Si: 11
}
const NOTE = '(Do|Ré|Re|Mi|Fa|Sol|La|Si|[A-G])(#|b|♯|♭)?'
const CHORD = new RegExp(`^${NOTE}([^/]*)(?:/${NOTE})?$`)
const KEY = new RegExp(`^${NOTE}(.*)$`)
// Major keys by pitch class that are written with flats, then minor ones.
const FLAT_MAJOR_KEYS = [5, 10, 3, 8, 1, 6]
const FLAT_MINOR_KEYS = [2, 7, 0, 5, 10, 3]
const MINOR_SUFFIX = /^\s*(m(?!aj)|min|mineur|minor|-)/i

function pitchOf(natural, accidental) {
  const shift =
    accidental === '#' || accidental === '♯' ? 1 : accidental === 'b' || accidental === '♭' ? -1 : 0
  return (NATURALS[natural] + shift + 12) % 12
}

function spell(pitch, latin, flats) {
  const names = latin
    ? flats
      ? LATIN_FLATS
      : LATIN_SHARPS
    : flats
      ? ENGLISH_FLATS
      : ENGLISH_SHARPS
  return names[((pitch % 12) + 12) % 12]
}

const isLatin = (natural) => natural.length > 1

/** Whether the key the song lands in is written with flats; with no known key, the direction decides. */
export function prefersFlats(targetKey, steps) {
  const match = (targetKey ?? '').trim().match(KEY)
  if (!match) return steps < 0
  const pitch = pitchOf(match[1], match[2])
  return MINOR_SUFFIX.test(match[3])
    ? FLAT_MINOR_KEYS.includes(pitch)
    : FLAT_MAJOR_KEYS.includes(pitch)
}

/** A chord moved by `steps` semitones, or unchanged when it is not a chord this can read (`N.C.`). */
export function transposeChord(name, steps, flats) {
  const match = name.match(CHORD)
  if (!match || steps % 12 === 0) return name
  const [, root, accidental, quality, bass, bassAccidental] = match
  let out = spell(pitchOf(root, accidental) + steps, isLatin(root), flats) + quality
  if (bass) out += `/${spell(pitchOf(bass, bassAccidental) + steps, isLatin(bass), flats)}`
  return out
}

/** The song's key moved by `steps`, keeping whatever follows the note (« m », « mineur »), or null. */
export function transposeKey(key, steps) {
  const match = (key ?? '').trim().match(KEY)
  if (!match) return null
  const pitch = pitchOf(match[1], match[2]) + steps
  const latin = isLatin(match[1])
  const flats = prefersFlats(spell(pitch, latin, false) + match[3], steps)
  return spell(pitch, latin, flats) + match[3]
}

/** Every chord in the source moved by `steps`. Only chords are rewritten, the rest stays byte for byte. */
export function transposeSource(source, steps, key) {
  const flats = prefersFlats(transposeKey(key, steps), steps)
  return (source ?? '')
    .split('\n')
    .map((line) =>
      DIRECTIVE.test(line.trim())
        ? line
        : line.replace(
            /\[([^\]]+)\]/g,
            (_, name) => `[${transposeChord(name.trim(), steps, flats)}]`
          )
    )
    .join('\n')
}

// ---------------------------------------------------------------------------------------------
// Paste. Chord sites mostly write chords on their own line over the lyric, with `[Verse 1]` style
// headers, which read as a chord in ChordPro. Converted on the way in; ChordPro passes untouched.

const CHORD_TOKEN = new RegExp(
  `^${NOTE}(?:m|maj|min|dim|aug|sus|add|M|°|ø|\\+)?[0-9#b+()\\-]*(?:sus\\d|add\\d|maj\\d)?(?:/${NOTE})?$`
)
const FILLER_TOKEN = /^(\||\|\||-|x\d+|\(x\d+\)|N\.?C\.?|%)$/i
const HEADER =
  /^\s*\[?\s*(intro|outro|solo|interlude|break(?:down)?|pre-?chorus|pr[ée]-?refrain|chorus|refrain|verse|couplet|bridge|pont)\s*(\d*)\s*\]?\s*:?\s*$/i

function isChordLine(line) {
  const tokens = line.trim().split(/\s+/).filter(Boolean)
  return (
    tokens.length > 0 &&
    tokens.some((token) => CHORD_TOKEN.test(token)) &&
    tokens.every((token) => CHORD_TOKEN.test(token) || FILLER_TOKEN.test(token))
  )
}

// A chord site's header, in English mostly, read as one of our parts.
const HEADER_KINDS = [
  [/^pre-?chorus$|^pr[ée]-?refrain$/, 'prechorus'],
  [/^(chorus|refrain)$/, 'chorus'],
  [/^(verse|couplet)$/, 'verse'],
  [/^(bridge|pont)$/, 'bridge'],
  [/^break(down)?$/, 'break']
]

function sectionFor(word) {
  const lower = word.toLowerCase()
  return HEADER_KINDS.find(([pattern]) => pattern.test(lower))?.[1] ?? lower
}

// The part's own name is implied by its kind, so only a number or a different word (« Breakdown ») is kept.
function labelFor(word, number, kind) {
  const kindLabel = SECTION_KINDS.find((candidate) => candidate.value === kind).label
  const isKindWord = sectionFor(word) === kind && !/^breakdown$/i.test(word)
  const label = isKindWord ? kindLabel : word.charAt(0).toUpperCase() + word.slice(1).toLowerCase()
  if (number) return `${label} ${number}`
  return label === kindLabel ? '' : label
}

function mergeChords(chordLine, lyric) {
  let merged = lyric
  const chords = [...chordLine.matchAll(/\S+/g)]
    .filter((match) => CHORD_TOKEN.test(match[0]))
    .reverse()
  for (const { 0: name, index } of chords) {
    if (index > merged.length) merged = merged.padEnd(index)
    merged = `${merged.slice(0, index)}[${name}]${merged.slice(index)}`
  }
  return merged.trimEnd()
}

export function fromChordSite(text) {
  const lines = (text ?? '').replace(/\r\n?/g, '\n').split('\n')
  const out = []
  let openSection = null

  for (let index = 0; index < lines.length; index++) {
    const line = lines[index]
    const header = line.match(HEADER)
    if (header) {
      if (openSection) out.push(`{end_of_${openSection}}`)
      openSection = sectionFor(header[1])
      const label = labelFor(header[1], header[2], openSection)
      out.push(label ? `{start_of_${openSection}: ${label}}` : `{start_of_${openSection}}`)
      continue
    }
    if (isChordLine(line)) {
      const next = lines[index + 1]
      if (next !== undefined && next.trim() !== '' && !isChordLine(next) && !HEADER.test(next)) {
        out.push(mergeChords(line, next))
        index++
      } else {
        out.push(mergeChords(line, ''))
      }
      continue
    }
    out.push(line)
  }
  if (openSection) out.push(`{end_of_${openSection}}`)

  return out.join('\n')
}

// ---------------------------------------------------------------------------------------------
// Colours. Picked by the order the lyrics first name each singer, so the drawer and the PDF agree
// without storing anything. Mirrors App\Service\BandSpace\Song\ChordPro\SingerPalette.

export const SINGER_PALETTE = [
  '#4f46e5',
  '#b45309',
  '#047857',
  '#e11d48',
  '#0369a1',
  '#7c3aed',
  '#4d7c0f',
  '#c2410c'
]
export const SINGER_ALL_COLOR = '#64748b'

/** The colour of every singer id of the song, `all` included. */
export function singerColors(ids) {
  const colors = {}
  let index = 0
  for (const id of ids) {
    colors[id] =
      id === SINGER_ALL ? SINGER_ALL_COLOR : SINGER_PALETTE[index++ % SINGER_PALETTE.length]
  }
  return colors
}

/** The CSS background of a passage: the singer's colour faded to 20%, stripes when several share it. */
export function singerTint(singers, colors) {
  const shades = singers.map((id) => colors[id] ?? SINGER_ALL_COLOR)
  if (shades.length === 1) return `${shades[0]}33`
  const step = 100 / shades.length
  return `linear-gradient(180deg, ${shades.map((color, index) => `${color}40 ${index * step}% ${(index + 1) * step}%`).join(', ')})`
}
