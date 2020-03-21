<template>
    <b-modal id="modal-gallery-add" title="Ajouter une galerie">

        <b-form>
            <b-input v-model="name" placeholder="Le titre de la galerie"></b-input>

            <b-form-text>La galerie ne sera pas mise en ligne directement</b-form-text>
        </b-form>

        <template slot="modal-footer" slot-scope="{ ok, cancel, hide }">
            <b-button v-if="!saved" variant="default" @click="cancel()">
                Annuler
            </b-button>

            <b-button v-if="!saved" variant="outline-success" @click="saveGallery" :disabled="submitted">
                <b-spinner small v-if="submitted"></b-spinner>
                <i class="far fa-save" v-else></i>
                Enregistrer
            </b-button>

            <b-button v-if="saved" variant="outline-success" :to="editUrl">
                Editer la publication
            </b-button>

        </template>
    </b-modal>
</template>

<script>

  export default {
    data() {
      return {
        name: '',
        saved: false,
        submitted: false,
      }
    },
    methods: {
      saveGallery() {

      }
    }
  }
</script>