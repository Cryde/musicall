<template>
    <b-modal id="modal-edit-gallery-properties" ref="modal-edit-gallery-properties" title="Paramètres de la galerie">

        <b-form-group description="Le titre de votre galerie">
            <b-form-input v-model="currentTitle"
                          placeholder="Votre titre ici"></b-form-input>
        </b-form-group>

        <b-form-group description="Description de votre galerie">
            <b-form-textarea v-model="currentDescription"
                             placeholder=""></b-form-textarea>
        </b-form-group>

        <div v-if="coverImage">
            Image de couverture de la galerie.
            <b-img :src="coverImage.sizes.medium" fluid class="mt-2"/>
        </div>
        <div v-else>
            Vous n'avez pas encore défini d'image de couverture.<br/>

            <span v-if="images.length">Vous pouvez le faire en cliquant sur l'icone <span class="btn btn-primary"><i
                    class="fas fa-image"></i></span> depuis les images envoyés ci dessous.</span>
            <span v-else>Vous devez uploader des images pour pouvoir definir une image de couverture</span>
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
        currentDescription: '',
        submitted: false,
      }
    },
    computed: {
      ...mapGetters('userGallery', [
        'gallery',
        'coverImage',
        'images'
      ])
    },
    mounted() {
      this.currentTitle = this.gallery.title;
      this.currentDescription = this.gallery.description;
    },
    methods: {
      async save() {
        this.submitted = true;
        await this.$store.dispatch('userGallery/edit', {
          title: this.currentTitle,
          description: this.currentDescription,
          id: this.gallery.id
        });
        this.submitted = false;
        this.$bvModal.hide('modal-edit-gallery-properties');
      }
    }
  };
</script>