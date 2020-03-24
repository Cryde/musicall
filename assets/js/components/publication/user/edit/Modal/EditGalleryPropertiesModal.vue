<template>
    <b-modal id="modal-edit-gallery-properties" ref="modal-edit-gallery-properties" title="Paramètres de la galerie">

        <b-form-group description="Le titre de votre publication">
            <b-form-input v-model="currentTitle"
                          placeholder="Votre titre ici"></b-form-input>
        </b-form-group>

        <div>
            Image de couverture de la galerie
        </div>


        <div slot="modal-footer" slot-scope="{ cancel}">
            <b-button @click="cancel()" variant="default">Annuler</b-button>
            <b-button
                    @click="save"
                    variant="success"
                    :disabled="submitted"
            >
                <b-spinner small v-if="submitted"></b-spinner>
                <i class="far fa-save" v-else></i>
                Enregistrer
            </b-button>
        </div>
    </b-modal>

</template>

<script>
  import {mapGetters} from "vuex";

  export default {
    data() {
      return {
        currentTitle: '',
        submitted: false,
      }
    },
    computed: {
      ...mapGetters('userGallery', [
        'gallery',
      ])
    },
    mounted() {
      this.currentTitle = this.gallery.title;
    },
    methods: {
      async save() {
        this.submitted = true;
        await this.$store.dispatch('userGallery/edit', {title: this.currentTitle, id: this.gallery.id});
        this.submitted = false;
        this.$bvModal.hide('modal-edit-gallery-properties');
      }
    }
  };
</script>