<template>
  <b-step-item :step="1" label="Instrument" icon="guitar">

    <h3 class="subtitle mb-5" v-if="selectedType">{{ titles[selectedType].instrument }}</h3>

    <span v-for="instrument in instruments"
          class="box is-clickable is-inline-block mb-1 pl-4 pr-4 pt-2 pb-2 mr-1"
          :class="[selectedInstrument.id === instrument.id ? 'has-background-info has-text-white' : 'has-background-info-light has-text-info']"
          @click="selectInstrument(instrument)"
    >
                {{ instrument.name }}
            </span>
  </b-step-item>
</template>

<script>
import {mapGetters} from "vuex";

export default {
  props: ['titles'],
  computed: {
    ...mapGetters('instruments', ['instruments']),
    ...mapGetters('announceMusician', ['selectedInstrument', 'selectedType']),
  },
  methods: {
    selectInstrument(instrument) {
      this.$store.dispatch('announceMusician/updateSelectedInstruments', {instrument});
      this.$emit('next-step');
    },
  }
}
</script>