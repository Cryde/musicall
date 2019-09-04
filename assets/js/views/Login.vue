<template>
    <div>
        <form method="post" class="form-signin">
            <h1 class="h3 mb-3 font-weight-normal">Please sign in</h1>
            <label for="inputUsername" class="sr-only">Username</label>
            <input v-model="username" type="text" name="login" id="inputUsername" class="form-control"
                   placeholder="Username" required autofocus>
            <label for="inputPassword" class="sr-only">Password</label>
            <input v-model="password" type="password" name="password" id="inputPassword" class="form-control"
                   placeholder="Password" required>

            <button
                    :disabled="!username.length || !password.length || isLoading"
                    class="btn btn-lg btn-primary" type="submit" @click.prevent @click="login">
                <b-spinner variant="primary" type="grow" label="Spinning" v-if="isLoading"></b-spinner>
                Se connecter
            </button>
        </form>
    </div>
</template>

<script>
  import {mapGetters} from 'vuex';

  export default {
    data() {
      return {
        username: '',
        password: '',
      }
    },
    computed: {
      ...mapGetters('security', [
        'isLoading',
        'hasError',
        'error'
      ])
    },
    methods: {
      login() {
        const username = this.username;
        const password = this.password;
        const redirect = this.$route.query.redirect;

        this.$store.dispatch('security/login', {username, password});

        if (!this.$store.getters["security/hasError"]) {
          if (typeof redirect !== "undefined") {
            this.$router.push({path: redirect});
          } else {
            this.$router.push({name: "home"});
          }
        }
      },
    }
  }
</script>