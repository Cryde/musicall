<template>
  <b-step-item :step="5" label="Résumé" icon="check-square">

    <h2 class="mb-3 subtitle is-5">Récapitulatif</h2>
    <div class="preview" v-if="selectedType === 'band'">
      Je suis un-e <strong>{{ selectedInstrument.musician_name.toLocaleLowerCase() }}</strong> et
      je recherche un groupe jouant du
      <strong>{{
          selectedStyles.map(style => style.name.toLocaleLowerCase()).join(', ')
        }}</strong> dans les alentours de <strong>{{ selectedLocationName }}</strong>.
    </div>
    <div class="preview" v-else>
      Je recherche un-e <strong>{{
        selectedInstrument.musician_name.toLocaleLowerCase()
      }}</strong> jouant du
      <strong>{{
          selectedStyles.map(style => style.name.toLocaleLowerCase()).join(', ')
        }}</strong> dans les alentours de <strong>{{ selectedLocationName }}</strong>.
    </div>
    <div v-if="note.length" class="mt-2" style="white-space: pre">Avec comme note : <br/>{{ note }}</div>
  </b-step-item>
</template>

<script>
import {mapGetters} from "vuex";

export default {
  computed: {
    ...mapGetters('announceMusician', ['selectedInstrument', 'selectedStyles', 'selectedType', 'selectedLocationName', 'note']),
  }
}
</script>