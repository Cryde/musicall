<template>
    <div>
        <div v-if="hasError" class="row">
            <div class="col-12 col-lg-4 offset-lg-4">
                <div class="alert alert-danger" role="alert">
                    {{ error.message }}
                </div>
            </div>
        </div>
        <div class="row">
            <form method="post" class="col-lg-4 col-12 offset-lg-4 form-signin">
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
      async login() {
        const username = this.username;
        const password = this.password;
        const redirect = this.$route.query.redirect;

        await this.$store.dispatch('security/login', {username, password});

        if (!this.hasError) {
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