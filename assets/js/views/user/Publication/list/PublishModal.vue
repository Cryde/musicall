<template>
  <b-modal id="modal-publication-control" centered title="Publier la publication">
    <div v-if="loading" class="p-5 has-text-centered">
      <b-spinner label="Spinning"></b-spinner>
    </div>
    <div v-else>
      <div v-if="errors.length">
        <ul>
          <li v-for="error in errors">{{ error }}</li>
        </ul>
      </div>
      <div v-else class="has-text-centered">
        <i class="fas fa-check fa-5x text-success mb-3"></i><br/>
        Votre publication a été publiée !
      </div>
    </div>


    <template slot="modal-footer" slot-scope="{ ok, cancel, hide }">
      <b-button variant="default" @click="cancel()">
        Fermer
      </b-button>
    </template>
  </b-modal>
</template>

<script>
  export default {
    props: {
      errors: Array,
      loading: Boolean
    }
  }
</script>