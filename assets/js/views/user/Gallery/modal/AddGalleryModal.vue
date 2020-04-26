<template>
    <b-modal id="modal-gallery-add" ref="modal-gallery-add" title="Ajouter une galerie">

        <b-form v-if="!saved">
            <b-input v-model="name" placeholder="Le titre de la galerie"></b-input>

            <b-alert variant="warning" :show="true" class="mt-3">
                <i class="fas fa-exclamation"></i>
                Privilégiez une forme de titre comme suit : <br/>
                Artiste - date - salle/festival - ville<br/>
                <strong>Exemple :</strong><br/>
                Metallica - 30 mars 2020 - Botanique - Bruxelles<br/>
                Metallica - 30 juin 2020 - Rock Werchter - Werchter
            </b-alert>

            <b-form-text text-variant="info">
                La galerie ne sera pas mise en ligne directement.<br/>
                Vous pourrez encore modifier le titre plus tard
            </b-form-text>
        </b-form>
        <div v-else class="text-center">
            <i class="fas fa-check fa-5x text-success mb-3"></i><br/>
            La galerie a été créée.<br/>
            Vous pouvez désormais lui ajouter des photos
        </div>

        <template slot="modal-footer" slot-scope="{ ok, cancel, hide }">
            <b-button v-if="!saved" variant="default" @click="cancel()">
                Annuler
            </b-button>

            <b-button v-if="!saved" variant="outline-success" @click="saveGallery" :disabled="submitted">
                <b-spinner small v-if="submitted"></b-spinner>
                <i class="far fa-save" v-else></i>
                Enregistrer
            </b-button>

            <b-button v-if="saved" variant="outline-success" :to="galleryUrl">
                Editer la galerie
            </b-button>

        </template>
    </b-modal>
</template>

<script>
  import galleryApi from "../../../../api/publication/gallery";

  export default {
    data() {
      return {
        name: '',
        saved: false,
        submitted: false,
        galleryUrl: null
      }
    },
    mounted() {
      this.$refs['modal-gallery-add'].$on('hidden', () => {
        this.reset();
      });
    },
    methods: {
      async saveGallery() {
        try {
          this.submitted = true;
          const gallery = await galleryApi.addGallery({title: this.name});
          await this.$store.dispatch('userGalleries/load');
          this.galleryUrl = {name: 'user_gallery_edit', params: {id: gallery.id}};
          this.saved = true;
        } catch (e) {
          this.submitted = false;
          console.error(e);
        }
      },
      reset() {
        this.name = '';
        this.saved = false;
        this.submitted = false;
        this.galleryUrl = null;
      }
    }
  }
</script>