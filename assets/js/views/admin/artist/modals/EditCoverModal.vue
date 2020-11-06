<template>
    <b-modal id="modal-upload-wiki-artist-cover" centered ref="modal-upload-wiki-artist-cover" size="lg" title="Uploader une image de cover pour l'artiste">

        <b-alert v-show="errors.length" variant="danger" show>
            <span v-for="error in errors" class="is-block">{{ error }}</span>
        </b-alert>

        <p>
            L'image doit faire au minimum 300px de haut et 1000px de large. Max 4Mb.<br/>
            Nous recommandons 1500x360
        </p>

        <vue-dropzone
                v-if="dropzoneOptions"
                id="dropzone"
                @vdropzone-success="uploaded"
                @vdropzone-error="error"
                @vdropzone-file-added="start"
                :options="dropzoneOptions"
        >
        </vue-dropzone>

        <div slot="modal-footer">
            <b-button @click="hideModal">Annuler</b-button>
            <b-button
                    variant="success"
                    v-b-tooltip.hover
                    :title="validImage ? '' : 'L\'image ne semble pas être valide'"
                    :disabled="!validImage"
            >
                Ok
            </b-button>
        </div>
    </b-modal>

</template>

<script>
  import vue2Dropzone from "vue2-dropzone";
  import {EVENT_ADMIN_UPDATE_ARTIST_COVER} from "../../../../constants/events";

  export default {
    components: {
      vueDropzone: vue2Dropzone
    },
    props: ['artistId'],
    data() {
      return {
        imageSrc: "",
        dropzoneOptions: null,
        errors: [],
      };
    },
    async mounted() {
      this.$refs['modal-upload-wiki-artist-cover'].$on('show', async () => {
        const url = Routing.generate('api_admin_artist_upload_cover', {id: this.artistId});
        this.dropzoneOptions = {
          url,
          headers: {'Authorization': 'Bearer ' + await this.$store.dispatch('security/getAuthToken', {displayLoading: false})},
          paramName: 'image_upload[imageFile][file]',
          thumbnailWidth: 200,
          maxFilesize: 4,
          dictFileTooBig: 'Le fichier est trop volumineux ({{filesize}}M). Sa taille ne doit pas dépasser {{maxFilesize}} M.',
          dictDefaultMessage: "<i class=\"fas fa-cloud-upload-alt\"></i> Uploader une image"
        };
      });
    },
    computed: {
      validImage() {
        return this.imageSrc.match(/\.(jpeg|jpg|png)$/) != null && this.errors.length === 0;
      }
    },
    methods: {
      hideModal() {
        this.imageSrc = "";
        this.$refs['modal-upload-wiki-artist-cover'].hide()
      },
      error(error, messages) {
        if (!Array.isArray(messages)) {
          this.errors.push(messages);
          return;
        }

        for (const message of messages) {
          this.errors.push(message.message);
        }
      },
      start() {
        this.errors = [];
      },
      async uploaded(file, resp) {
        this.imageSrc = resp.cover;
        this.$root.$emit(EVENT_ADMIN_UPDATE_ARTIST_COVER, resp.cover);
        this.hideModal();
      },
    }
  };
</script>