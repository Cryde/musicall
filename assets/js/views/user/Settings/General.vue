<template>
  <div v-if="isLoading" class="text-center p-5">
    <b-spinner/>
  </div>
  <b-card v-else title="Paramètres généraux du compte">
    <b-row class="mt-5 mb-3">
      <b-col xl="5">Nom d'utilisateur</b-col>
      <b-col xl="7">{{ user.username }}</b-col>
    </b-row>
    <b-row class="mt-4 mb-4">
      <b-col xl="5">Adresse email</b-col>
      <b-col xl="7">{{ user.email }}</b-col>
    </b-row>
    <b-row class="mt-4 mb-4">
      <b-col xl="5">Photo de profil</b-col>
      <b-col xl="7">
        <b-button @click="$refs.file.click()">
          <span v-if="!user.picture"><i class="far fa-image"></i> Ajouter une photo de profile</span>
          <span v-else><i class="far fa-image"></i> Modifier ma photo de profile</span>
          <input type="file" class="d-none" ref="file" @change="uploadImage($event)" accept="image/*">
        </b-button>
      </b-col>
    </b-row>
    <profile-picture-modal v-if="image" :image="image"/>
  </b-card>
</template>

<script>
import {mapGetters} from 'vuex';
import ProfilePictureModal from "./Picture/ProfilePictureModal";
import {EVENT_PROFILE_PICTURE_MODAL_CLOSE, EVENT_PROFILE_PICTURE_SUCCESS} from "../../../constants/events";

export default {
  components: {ProfilePictureModal},
  data() {
    return {
      image: null,
    }
  },
  computed: {
    ...mapGetters('user', ['isLoading', 'user'])
  },
  async mounted() {
    this.$root.$on(EVENT_PROFILE_PICTURE_MODAL_CLOSE, () => {
      this.image = null;
    });

    this.$root.$on(EVENT_PROFILE_PICTURE_SUCCESS, () => {
      this.$bvToast.toast('Votre photo de profile a été mise à jour', {
        title: `Photo de profile`,
        variant: 'success',
        solid: true,
        toaster: 'b-toaster-bottom-left',
        append: true
      });
    });
  },
  methods: {
    uploadImage(event) {
      const input = event.target;
      if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
          this.image = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
      }
    }
  },
  destroyed() {
    this.$root.$off(EVENT_PROFILE_PICTURE_MODAL_CLOSE);
    this.$root.$off(EVENT_PROFILE_PICTURE_SUCCESS);
  }
}
</script>