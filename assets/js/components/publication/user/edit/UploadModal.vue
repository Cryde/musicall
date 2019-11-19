<template>
    <b-modal id="modal-upload-image" centered ref="upload-modal" size="lg" title="Uploader une image">

        <vue-dropzone
                v-if="dropzoneOptions"
                ref="myVueDropzone"
                id="dropzone"
                @vdropzone-success="vfileUploaded"
                :options="dropzoneOptions"
        >
        </vue-dropzone>


        <div slot="modal-footer">
            <b-button @click="hideModal">Annuler</b-button>
            <b-button
                    @click="insertImage"
                    variant="success"
                    :title="validImage ? '' : 'L\'image ne semble pas être valide'"
                    :disabled="!validImage"
            >
                Ajouter l'image
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
    props: ['id'],
    data() {
      return {
        imageSrc: "",
        command: null,
        dropzoneOptions: null
      };
    },
    async mounted() {
      this.$refs['upload-modal'].$on('show', async () => {
        const url = Routing.generate('api_user_publication_upload_image', {id: this.id});
        this.dropzoneOptions = {
          url,
          headers: {'Authorization': 'Bearer ' + await this.$store.dispatch('security/getAuthToken', {displayLoading: false})},
          paramName: 'image_upload[imageFile][file]',
          thumbnailWidth: 200,
          dictDefaultMessage: "<i class=\"fas fa-cloud-upload-alt\"></i> Uploader une image"
        };
      });
    },
    computed: {
      validImage() {
        return this.imageSrc.match(/\.(jpeg|jpg|gif|png)$/) != null;
      }
    },
    methods: {
      openModal(command) {
        this.command = command;
        this.$refs['upload-modal'].show()
      },
      hideModal() {
        this.imageSrc = "";
        this.$refs['upload-modal'].hide()
      },
      vfileUploaded(file, resp) {
        this.imageSrc = resp.data.uri;
      },
      insertImage() {
        const data = {
          command: this.command,
          data: {
            src: this.imageSrc,
          }
        };

        this.$emit("onConfirm", data);
        this.hideModal();
      }
    }
  };
</script>