<template>
    <div id="app">
        <Header/>
        <div class="container">
            <div class="row">
                <div class="col-12 col-lg-2">
                    <Menu/>
                </div>
                <div class="col-12 col-lg-10" v-if="isReadyWithMinimal">
                    <fade-transition :duration="100" origin="center top" mode="out-in">
                        <router-view></router-view>
                    </fade-transition>
                </div>
            </div>
        </div>
        <Footer/>
    </div>
</template>

<script>
  import Header from './components/global/Header';
  import Menu from './components/global/Menu';
  import Footer from './components/global/Footer';
  import axios  from 'axios';
  import {mapGetters} from 'vuex';
  import {FadeTransition} from 'vue2-transitions'

  export default {
    data() {
      return {
        isReadyWithMinimal: false
      }
    },
    name: 'app',
    components: {
      Header, Footer, Menu, FadeTransition
    },
    computed: {
      ...mapGetters('security', ['isAuthenticated'])
    },
    async created() {

      await this.$store.dispatch('security/getAuthToken', {displayLoading: true});
      this.isReadyWithMinimal = true;
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

          if(!token && currentRoute.meta.isAuthRequired) {
            await router.replace({name:'home'});
          }
        }

        return config;
      }, function (error) {
        return Promise.reject(error);
      });

      if(this.isAuthenticated) {
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