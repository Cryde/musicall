<template>
  <b-step-item :step="4" label="Note" icon="pencil-alt">
    <h2 class="mb-3 subtitle is-5">Détails supplémentaire</h2>

    <div class="has-text-info mb-3">
      Ajoutez des détails ici. Ex : groupe favoris, lien vers un morceau, ...<br/>
      Ce n'est pas requis.
    </div>

    <b-input
        type="textarea"
        @keyup.native="updateNote"
        rows="3" max-rows="5"
        placeholder=""></b-input>
  </b-step-item>
</template>

<script>
export default {
  methods: {
    updateNote(elem) {
      this.$store.dispatch('announceMusician/updateNote', elem.target.value);
    },
    next() {
      this.$emit('next-step');
    }
  }
}
</script>