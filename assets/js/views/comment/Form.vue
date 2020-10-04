<template>
    <b-row v-if="isAuthenticated && !isLoading">
        <b-col cols="12" v-show="errors.length">
            <b-alert variant="danger" class="mt-3" show>
                <span v-for="error in errors" class="d-block">{{ error }}</span>
            </b-alert>
        </b-col>

        <b-col cols="2" class="text-center">
          <avatar :user="user" :is-loading="isLoading" size="4em" />
          <strong class="mt-2 d-block">{{ user.username }}</strong>
        </b-col>
        <b-col cols="10">
            <b-textarea v-model="content"></b-textarea>

            <b-button variant="primary" class="float-right mt-3" @click="addComment" :disabled="isAddingComment || !enableAddButton">
                <b-spinner small v-if="isAddingComment"/>
                <i v-else class="far fa-paper-plane"></i>
                Poster le commentaire
            </b-button>
        </b-col>
    </b-row>
    <b-row v-else>
        <b-col cols="12" class="text-center pb-5">
            Vous devez
            <router-link :to="{name: 'login'}">être connecté</router-link>
            ou
            <router-link :to="{name: 'user_registration'}">inscrit</router-link>
            pour pouvoir poster un commentaire
        </b-col>
    </b-row>
</template>

<script>
  import {mapGetters} from "vuex";
  import Avatar from "../../components/user/Avatar";

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
          this.errors = e.response.data.violations.map(violation => violation.title);
        }
        this.isAddingComment = false
      }
    }
  }
</script>