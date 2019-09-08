<template>
    <b-modal id="modal-video" centered ref="video-modal" size="lg" title="Ajouter une video Youtube">
        <div class="row">
            <div class="col-lg-12">
                <b-form-input v-model="url"  placeholder="ex: https://www.youtube.com/watch?v=yoWDxUVIHPU" type="url"></b-form-input>
            </div>
        </div>


        <div slot="modal-footer">
            <b-button @click="hideModal">Annuler</b-button>
            <b-button
                    @click="insertVideo"
                    variant="success"
                    :title="validYoutubeID ? '' : 'L\'url de la vidéo ne semble pas être valide'"
                    :disabled="!validYoutubeID"
            >
                Ajouter la video
            </b-button>
        </div>
    </b-modal>

</template>

<script>
  export default {
    data() {
      return {
        url: "",
        command: null
      };
    },
    mounted() {

    },
    computed: {
      youtubeID() {
        return this.youtubeParser(this.url);
      },
      validYoutubeID() {
        return this.youtubeParser(this.url).length;
      }
    },
    methods: {
      youtubeParser(url) {
        const regExp = /^.*((youtu.be\/)|(v\/)|(\/u\/\w\/)|(embed\/)|(watch\?))\??v?=?([^#\&\?]*).*/;
        const match = url.match(regExp);
        return match && match[7].length === 11 ? match[7] : false;
      },
      openModal(command) {
        // Add the sent command
        this.command = command;
        this.$refs['video-modal'].show()
      },
      hideModal() {
        this.url = "";
        this.$refs['video-modal'].hide()
      },
      insertVideo() {
        const data = {
          command: this.command,
          data: {
            src:  `https://www.youtube.com/embed/${this.youtubeID}`
          }
        };

        this.$emit("onConfirm", data);
        this.hideModal();
      }
    }
  };
</script>