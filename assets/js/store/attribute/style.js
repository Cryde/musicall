import { defineStore } from 'pinia'
import { readonly, ref } from 'vue'
import styleApi from '../../api/attribute/style.js'

export const useStyleStore = defineStore('styles', () => {
  const styles = ref([])

  async function loadStyles() {
    styles.value = await styleApi.listStyle()
  }

  function clear() {
    styles.value = []
  }

  return {
    loadStyles,
    clear,
    styles: readonly(styles)
  }
})
