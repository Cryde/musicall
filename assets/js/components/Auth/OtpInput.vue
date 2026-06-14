<template>
  <div class="flex gap-2 justify-center" role="group" aria-label="Code de vérification à 6 chiffres">
    <input
      v-for="(_, index) in digits"
      :key="index"
      :ref="(el) => (inputRefs[index] = el)"
      v-model="digits[index]"
      :aria-label="`Chiffre ${index + 1}`"
      type="text"
      inputmode="numeric"
      maxlength="1"
      class="w-12 h-14 text-center text-xl font-semibold rounded-lg border border-surface-300 dark:border-surface-600 bg-surface-0 dark:bg-surface-900 text-surface-900 dark:text-surface-0 focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-all"
      :class="{ 'border-red-500 dark:border-red-400': hasError }"
      :disabled="disabled"
      @input="handleInput(index)"
      @keydown="handleKeydown(index, $event)"
      @paste="handlePaste($event)"
    />
  </div>
</template>

<script setup>
import { nextTick, reactive, ref, watch } from 'vue'

const props = defineProps({
  hasError: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false }
})

const emit = defineEmits(['complete'])

const digits = reactive(['', '', '', '', '', ''])
const inputRefs = ref([])

function handleInput(index) {
  const value = digits[index]

  if (!/^\d$/.test(value)) {
    digits[index] = ''
    return
  }

  if (index < 5) {
    nextTick(() => inputRefs.value[index + 1]?.focus())
  }

  checkComplete()
}

function handleKeydown(index, event) {
  if (event.key === 'Backspace' && !digits[index] && index > 0) {
    nextTick(() => inputRefs.value[index - 1]?.focus())
  }
}

function handlePaste(event) {
  event.preventDefault()
  const pastedData = event.clipboardData.getData('text').trim()
  const pastedDigits = pastedData.replace(/\D/g, '').slice(0, 6)

  if (pastedDigits.length === 0) return

  for (let i = 0; i < 6; i++) {
    digits[i] = pastedDigits[i] || ''
  }

  const focusIndex = Math.min(pastedDigits.length, 5)
  nextTick(() => inputRefs.value[focusIndex]?.focus())

  checkComplete()
}

function checkComplete() {
  const code = digits.join('')
  if (code.length === 6 && /^\d{6}$/.test(code)) {
    emit('complete', code)
  }
}

function clear() {
  for (let i = 0; i < 6; i++) {
    digits[i] = ''
  }
  nextTick(() => inputRefs.value[0]?.focus())
}

function focus() {
  nextTick(() => inputRefs.value[0]?.focus())
}

defineExpose({ clear, focus })
</script>
