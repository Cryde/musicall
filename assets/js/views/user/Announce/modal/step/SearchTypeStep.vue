<template>
  <b-step-item :step="0" label="Recherche" icon="search">
    <h3 class="subtitle mb-5">Que rechercher vous ?</h3>
    <div class="columns mt-5">
      <div class="column is-6 has-text-centered">
        <div class="box choice-button p-5 is-clickable"
             :class="{'has-background-light': selectedType === 'musician'}"
             @click="selectSearch('musician')"
        >
          <figure class="image is-48x48 container mb-2">
            <img :src="musicianPath"/>
          </figure>
          Un musicien
        </div>
      </div>
      <div class="column is-6 has-text-centered">
        <div class="box choice-button p-5 is-clickable"
             :class="{'has-background-light': selectedType === 'band'}"
             @click="selectSearch('band')"
        >
          <figure class="image is-48x48 container mb-2">
            <img :src="bandPath"/>
          </figure>
          Un groupe
        </div>
      </div>
    </div>
  </b-step-item>
</template>

<script>
import {mapGetters} from "vuex";
import musicianPath from '../../../../../../images/announce/musician/musician.png';
import bandPath from '../../../../../../images/announce/musician/band.png';

export default {
  data() {
    return {musicianPath, bandPath}
  },
  computed: {
    ...mapGetters('announceMusician', ['selectedType']),
  },
  methods: {
    selectSearch(flow) {
      this.$store.dispatch('announceMusician/updateAnnounceType', flow);
      this.$emit('next-step');
    },
  }
}
</script>