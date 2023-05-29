<template>
  <div id="app">
    <Header/>
    <div class="container mt-5 mb-5">
      <div class="columns">
        <div class="column is-12">
          <router-view></router-view>
        </div>
      </div>
    </div>
    <Footer/>
    <vue-progress-bar></vue-progress-bar>
  </div>
</template>

<script>
import Header from './components/global/Header';
import Footer from './components/global/Footer';
import axios from 'axios';
import {mapGetters} from 'vuex';

export default {
  name: 'app',
  components: {
    Header, Footer
  },
  computed: {
    ...mapGetters('security', ['isAuthenticated'])
  },
  async created() {

    try {
      await this.$store.dispatch('security/getAuthToken', {displayLoading: true});
    } catch (e) {
      if (e.response.status === 401) {
        await this.$store.dispatch('security/logout');
        window.location.reload();
        return;
      }
    }

    this.$store.dispatch('publicationCategory/getCategories');

    const store = this.$store;
    const router = this.$router;

    axios.interceptors.request.use(async function (config) {
      const url = config.url;
      const currentRoute = router.history.current;

      if (!url.includes('login') && !url.includes('refresh') && !url.includes('registration')) {
        const token = await store.dispatch('security/getAuthToken', {displayLoading: false});
        if (token) {
          config.headers['Authorization'] = `Bearer ${token}`;
        }

        if (!token && currentRoute.meta.isAuthRequired) {
          await router.replace({name: 'home'});
        }
      }

      return config;
    }, function (error) {
      return Promise.reject(error);
    });

    if (this.isAuthenticated) {
      await this.$store.dispatch('user/load');
      this.$store.dispatch('notifications/loadNotifications');
    }

    /**
     async responseError(error) {

          console.error(error);

          // Prevent endless redirects (login is where you should end up)
          if (error.request !== undefined) {
            if (error.request.responseURL.includes('login')) {
              return Promise.reject(error)
            }
          }

          // If you can't refresh your token or you are sent Unauthorized on any request, logout and go to login
          if (error.request !== undefined && (error.request.responseURL.includes('refresh') || error.request.status === 401 && error.config.__isRetryRequest)) {
            //store.dispatch('auth/logout')
            //router.push({name: 'Login'})
          } else if (error.request !== undefined && error.request.status === 401) {
            //error.config.__isRetryRequest = true
            //return axios.request(error.config)
          }
          return Promise.reject(error)
        }
     */
  }
}
</script>