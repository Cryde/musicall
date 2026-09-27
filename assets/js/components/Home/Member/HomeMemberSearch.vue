<template>
  <div class="flex flex-col gap-3">
    <HomeSearchForm />
    <HomeFrequentSearches :searches="frequentSearches" />
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import searchApi from '../../../api/search/musician.js'
import HomeFrequentSearches from '../HomeFrequentSearches.vue'
import HomeSearchForm from '../HomeSearchForm.vue'

/** The search first, for a member who is still looking for musicians or a band (#1078). */
const frequentSearches = ref([])

// A row the search does without: a failure only leaves it out.
onMounted(async () => {
  try {
    frequentSearches.value = await searchApi.getFrequentSearches()
  } catch {
    frequentSearches.value = []
  }
})
</script>
