<template>
  <div class="modal-card">
    <header class="modal-card-head">
      <p class="modal-card-title">Ajouter une video</p>
      <button
          type="button"
          class="delete"
          @click="$emit('close')"/>
    </header>
    <section class="modal-card-body" :class="{'has-category': displayCategories}">

      <div v-if="displayCategories" class="mb-4 mt-4">
        <v-select
            :options="categories"
            v-model="selectedCategory"
            label="title"
            placeholder="Choissisez une catégorie"
        ></v-select>
      </div>

      <b-field message="Avec ce lien nous pourrons récupérer quelque informations">
        <b-input v-model="videoUrl"
                 @keyup.native="preview"
                 placeholder="Url de la video Youtube"></b-input>
      </b-field>

      <div class="has-text-centered" v-if="isLoading">
        <spinner/>
      </div>

      <div v-if="showPreview && !isExistingVideo">
        <b-field message="Le titre de la vidéo. Vous pouvez le changer">
          <b-input v-model="videoTitle"
                   placeholder="Titre de la video"></b-input>
        </b-field>

        <b-field message="La description de la vidéo. Vous pouvez la changer">
          <b-input v-model="videoDescription" type="textarea"
                   placeholder="Description de la video"></b-input>
        </b-field>

        <img :src="videoImage" class="img-fluid"/>
      </div>

      <div v-if="showPreview && isExistingVideo">
        La vidéo existe déjà. Vous ne pouvez pas la remettre en ligne une nouvelle fois.
      </div>
    </section>

    <footer class="modal-card-foot">
      <b-button type="is-light" @click="$emit('close')">Annuler</b-button>
      <b-button type="is-success"
                :loading="isLoadingAdd"
                icon-left="save"
                :disabled="!showPreview || isExistingVideo || !canSend" @click="save">
        Publier
      </b-button>
    </footer>
  </div>
</template>

<script>

import {mapGetters} from 'vuex';
import Spinner from "../../../../../components/global/misc/Spinner";
import {EVENT_PUBLICATION_CREATED} from "../../../../../constants/events";
import VSelect from 'vue-select';

export default {
  components: {Spinner, VSelect},
  props: {
    displayCategories: {
      type: Boolean,
      default: false,
    },
    categories: {
      type: Array,
      default: () => []
    },
    preSelectedCategory: {
      type: Object,
      default: null
    }
  },
  data() {
    return {
      selectedCategory: null,
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
    ]),
    canSend() {
      return this.videoUrl.startsWith('https://') && (!this.displayCategories || this.displayCategories && this.selectedCategory !== null);
    }
  },
  mounted() {
    this.selectedCategory = this.preSelectedCategory;
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
          imageUrl: this.videoImage,
          categoryId: this.displayCategories ? this.selectedCategory.id : null,
        });

        this.$emit('close');
        this.$root.$emit(EVENT_PUBLICATION_CREATED);

        this.$buefy.toast.open({
          message: 'Votre vidéo a été mise en ligne',
          type: 'is-success',
          position: 'is-bottom-left',
          duration: 5000
        });

      } catch (error) {
        console.error(error);
      }
    }
  },
  async destroyed() {
    await this.$store.dispatch('video/resetState');
  }
}
</script>
<style>
.has-category {
  min-height: 250px;
}
</style>