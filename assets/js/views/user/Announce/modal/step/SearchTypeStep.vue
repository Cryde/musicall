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
            <img src="/build/images/announce/musician/musician.png"/>
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
            <img src="/build/images/announce/musician/band.png"/>
          </figure>
          Un groupe
        </div>
      </div>
    </div>
  </b-step-item>
</template>

<script>
import {mapGetters} from "vuex";

export default {
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