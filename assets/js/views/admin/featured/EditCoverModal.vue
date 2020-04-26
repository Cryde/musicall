<template>
    <b-modal id="modal-upload-featured-image" centered ref="modal-upload-featured-image" size="lg" title="Uploader une image de cover">

        <b-alert v-show="errors.length" variant="danger" show>
            <span v-for="error in errors" class="d-block">{{ error }}</span>
        </b-alert>

        <p>
            L'image doit être de la taille 1500x360 précisement.
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

  export default {
    components: {
      vueDropzone: vue2Dropzone
    },
    props: ['featured'],
    data() {
      return {
        imageSrc: "",
        dropzoneOptions: null,
        errors: [],
      };
    },
    async mounted() {
      this.$refs['modal-upload-featured-image'].$on('show', async () => {
        const url = Routing.generate('api_admin_publication_featured_cover', {id: this.featured.id});
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
        return this.imageSrc.match(/\.(jpeg|jpg|gif|png)$/) != null && this.errors.length === 0;
      }
    },
    methods: {
      hideModal() {
        this.imageSrc = "";
        this.$refs['modal-upload-featured-image'].hide()
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
        this.imageSrc = resp.data.uri;
        await this.$store.dispatch('adminFeatured/refreshFeatured');
        this.hideModal();
      },
    }
  };
</script>