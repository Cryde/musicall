<template>
    <b-modal id="modal-publication-properties" size="lg" title="Propriété de la publication">

        <div>
            <b-form-group description="Le titre de votre publication">
                <b-form-input v-model="currentTitle" :state="validation.title.state"
                              placeholder="Votre titre ici"></b-form-input>
                <b-form-invalid-feedback :state="validation.title.state">
                    {{ validation.title.message }}
                </b-form-invalid-feedback>
            </b-form-group>

            <b-form-group description="Cette courte description apparaitra sur la page d'accueil">
                <b-form-textarea
                        v-model="currentDescription"
                        id="textarea"
                        placeholder="Une courte description de l'article"
                        rows="3"
                ></b-form-textarea>
            </b-form-group>

            <p class="alert alert-info">
                Attention l'image doit être carré et faire max 1500px de ooté.
            </p>
            <b-row>
                <b-col v-if="currentCover">
                    <b-img :src="currentCover" fluid-grow></b-img>
                </b-col>
                <b-col v-else>
                    Il n'y a pas encore de cover pour cette publication
                </b-col>
                <b-col>
                    <vue-dropzone
                            ref="myVueDropzone"
                            id="dropzone"
                            @vdropzone-success="vfileUploaded"
                            :options="dropzoneOptions"
                    >
                    </vue-dropzone>

                </b-col>
            </b-row>
        </div>


        <template slot="modal-footer" slot-scope="{ ok, cancel, hide }">
            <b-button variant="default" @click="cancel()">
                Annuler
            </b-button>

            <b-button variant="success" @click="save" class="float-right" :disabled="submitted">
                <b-spinner small v-if="submitted"></b-spinner>
                <i class="far fa-save" v-else></i>
                Enregistrer
            </b-button>
        </template>
    </b-modal>
</template>

<script>
  import vue2Dropzone from "vue2-dropzone";

  export default {
    components: {
      vueDropzone: vue2Dropzone
    },
    props: ['id', 'title', 'description', 'validation', 'cover', 'submitted'],
    data() {
      return {
        currentTitle: '',
        currentDescription: '',
        currentCover: '',
        dropzoneOptions: {
          url: Routing.generate('api_user_publication_upload_cover', {id: this.id}),
          paramName:'image_upload[imageFile][file]',
          thumbnailWidth: 200,
          dictDefaultMessage: "<i class=\"fas fa-cloud-upload-alt\"></i> Uploader une cover"
        }
      }
    },
    mounted(){
      this.currentTitle = this.title;
      this.currentDescription = this.description;
      this.currentCover = this.cover;
      console.log(this.cover);
    },
    methods: {
      save() {
        this.$emit('saveProperties', {title: this.currentTitle, description: this.currentDescription});
      },
      vfileUploaded(file, resp) {
        console.log(resp);
        this.currentCover = resp.data.uri;
      },
    }
  }
</script>