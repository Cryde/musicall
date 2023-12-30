<template>
  <div v-if="isAuthenticated && !isLoading">
    <div class="columns">
      <div class="column is-10 is-offset-1" v-show="errors.length">
        <b-message type="is-danger" class="mt-3" show>
          <span v-for="error in errors" class="is-block">{{ error }}</span>
        </b-message>
      </div>
    </div>

    <div class="columns">
      <div class="column is-10 is-offset-1">
        <article class="media">
          <figure class="media-left">
            <avatar :user="user" :is-loading="isLoading"/>
          </figure>
          <div class="media-content">
            <div class="field">
              <p class="control">
                <b-input type="textarea" v-model="content" placeholder="Ajouter un commentaire..."></b-input>
              </p>
            </div>
            <nav class="level">
              <div class="level-right">
                <div class="level-item">
                  <b-button type="is-info" class="mt-3" @click="addComment"
                            :loading="isAddingComment"
                            :disabled="isAddingComment || !enableAddButton">
                    <i class="far fa-paper-plane"></i>
                    Poster le commentaire
                  </b-button>
                </div>
              </div>
            </nav>
          </div>
        </article>
      </div>
    </div>
  </div>
  <div class="columns" v-else>
    <div class="column is-12 has-text-centered pb-5">
      Vous devez
      <router-link :to="{name: 'login'}">être connecté</router-link>
      ou
      <router-link :to="{name: 'user_registration'}">inscrit</router-link>
      pour pouvoir poster un commentaire
    </div>
  </div>
</template>

<script>
import {mapGetters} from "vuex";
import Avatar from "../../components/user/Avatar.vue";

export default {
  components: {Avatar},
  data() {
    return {
      content: '',
      isAddingComment: false,
      errors: [],
    }
  },
  computed: {
    ...mapGetters('security', ['isAuthenticated']),
    ...mapGetters('user', ['isLoading', 'user']),
    enableAddButton() {
      return this.content.trim().length > 0;
    }
  },
  methods: {
    async addComment() {
      this.errors = [];
      this.isAddingComment = true;
      try {
        await this.$store.dispatch('thread/postComment', {content: this.content});
        this.content = '';
      } catch (e) {
        this.errors = e.response.data.violations.map(violation => violation.message);
      }
      this.isAddingComment = false
    }
  }
}
</script>