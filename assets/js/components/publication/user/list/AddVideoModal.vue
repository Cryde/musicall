<template>
    <b-modal id="modal-video-add" title="Ajouter une video" ref="videoAddModal">

        <b-form-group description="Le titre de votre publication">
            <b-form-input v-model="videoUrl"
                          @keyup="preview"
                          placeholder="Url de la video Youtube"></b-form-input>
        </b-form-group>

        <div class="text-center" v-if="isLoading">
            <b-spinner/>
        </div>

        <div v-if="showPreview && !isExistingVideo">
            <b-form-group description="Le titre de la vidéo. Vous pouvez le changer">
                <b-form-input v-model="videoTitle"
                              placeholder="Titre de la video"></b-form-input>
            </b-form-group>

            <b-form-group description="La description de la vidéo. Vous pouvez la changer">
                <b-form-textarea v-model="videoDescription"
                                 placeholder="Description de la video"></b-form-textarea>
            </b-form-group>

            <img :src="videoImage" class="img-fluid"/>
        </div>

        <div v-if="showPreview && isExistingVideo">
            La vidéo existe déjà. Vous ne pouvez pas la remettre en ligne une nouvelle fois.
        </div>

        <template slot="modal-footer" slot-scope="{ ok, cancel, hide }">
            <b-button variant="default" @click="cancel()">
                Annuler
            </b-button>

            <b-button variant="outline-success" :disabled="!showPreview || isExistingVideo" @click="save">
                <b-spinner small v-if="isLoadingAdd"></b-spinner>
                <i class="far fa-save" v-else></i>
                Publier
            </b-button>
        </template>
    </b-modal>
</template>

<script>

  import {mapGetters} from 'vuex';

  export default {
    data() {
      return {
        showPreview: false,
        previousVideoUrl: '',
        videoUrl: '',
        videoTitle: '',
        videoDescription: '',
        videoImage: '',
      }
    },
    computed: {
      ...mapGetters('video', [
        'video',
        'isExistingVideo',
        'isLoading',
        'isLoadingAdd'
      ])
    },
    mounted() {
      this.$root.$on('bv::modal::hidden', async (bvEvent, modalId) => {
        if (modalId !== 'modal-video-add') {
          return;
        }

        await this.$store.dispatch('video/resetState');

        this.videoUrl = '';
        this.showPreview = false;
      });
    },
    methods: {
      async preview() {
        if (!this.videoUrl.trim()) {
          this.showPreview = false;
          this.previousVideoUrl = '';
          return;
        }

        if (this.previousVideoUrl.trim() === this.videoUrl.trim()) {
          return;
        }

        this.previousVideoUrl = this.videoUrl.trim();
        this.showPreview = false;

        await this.$store.dispatch('video/getPreviewVideo', {videoUrl: this.videoUrl.trim()});

        this.videoTitle = this.video.title;
        this.videoDescription = this.video.description;
        this.videoImage = this.video.image_url;

        this.showPreview = true;
      },
      async save() {
        try {
          await this.$store.dispatch('video/addVideo', {
            title: this.videoTitle,
            description: this.videoDescription,
            videoUrl: this.videoUrl,
            imageUrl: this.videoImage
          });

          this.$refs.videoAddModal.hide();
          this.$root.$emit('reload-table');

          this.$bvToast.toast('Votre vidéo a été mise en ligne', {
            title: `C'est en ligne !`,
            variant: 'success',
            solid: true,
            toaster: 'b-toaster-bottom-left',
            append: true
          });

        } catch(error) {
          console.error(error);
        }
      }
    }
  }
</script>