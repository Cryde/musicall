<template>
  <!-- Six strings and a few fret numbers: what a tab looks like, where PrimeIcons has nothing close. -->
  <svg width="72" height="52" viewBox="0 0 72 52" aria-hidden="true">
    <g stroke="currentColor" stroke-width="1.3" opacity="0.55">
      <line v-for="string in 6" :key="string" x1="2" x2="70" :y1="string * 8 - 2" :y2="string * 8 - 2" />
    </g>
    <g fill="currentColor" font-family="Open Sans, sans-serif" font-size="9" font-weight="700" text-anchor="middle">
      <text v-for="note in NOTES" :key="note.x" :x="note.x" :y="note.y">{{ note.fret }}</text>
    </g>
  </svg>
</template>

<script setup>
const NOTES = [
  { x: 14, y: 9, fret: 0 },
  { x: 26, y: 17, fret: 3 },
  { x: 38, y: 25, fret: 5 },
  { x: 50, y: 17, fret: 3 },
  { x: 62, y: 33, fret: 7 }
]
</script>
