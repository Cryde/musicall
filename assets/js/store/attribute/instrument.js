import { defineStore } from 'pinia'
import { readonly, ref } from 'vue'
import instrumentApi from '../../api/attribute/instrument.js'

export const useInstrumentStore = defineStore('instruments', () => {
  const instruments = ref([])

  async function loadInstruments() {
    instruments.value = await instrumentApi.listInstrument()
  }

  function clear() {
    instruments.value = []
  }

  return {
    loadInstruments,
    clear,
    instruments: readonly(instruments)
  }
})
