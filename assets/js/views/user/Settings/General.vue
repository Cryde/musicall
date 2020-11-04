<template>
  <div v-if="isLoading" class="has-text-centered p-5">
    <b-loading active/>
  </div>
  <div v-else class="card">
    <div class="card-content">

      <h3 class="subtitle is-4">Paramètres généraux du compte</h3>

      <div class="columns mt-5 mb-3">
        <div class="column is-3">Nom d'utilisateur</div>
        <div class="column is-9">{{ user.username }}</div>
      </div>
      <div class="columns mt-4 mb-4">
        <div class="column is-3">Adresse email</div>
        <div class="column is-9">{{ user.email }}</div>
      </div>
      <div class="columns mt-4 mb-4">
        <div class="column is-3">Photo de profil</div>
        <div class="column is-9">
          <b-button @click="$refs.file.click()" type="is-info" icon-left="image">
            <span v-if="!user.picture">Ajouter une photo de profile</span>
            <span v-else>Modifier ma photo de profile</span>
            <input type="file" class="is-hidden" ref="file" @change="uploadImage($event)" accept="image/*">
          </b-button>
        </div>
      </div>
      <profile-picture-modal v-if="image" :image="image" ref="profile-picture-modal"/>
    </div>
  </div>
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
      this.$buefy.toast.open({
        message: 'Votre photo de profile a été mise à jour',
        type: 'is-success',
        position: 'is-bottom-left',
        duration: 5000
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
          this.$nextTick(() => {
            this.$refs['profile-picture-modal'].open();
          })
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