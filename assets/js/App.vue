<template>
    <div id="app">
        <Header/>
        <div class="container">
            <div class="row">
                <div class="col-12 col-lg-2">
                    <Menu/>
                </div>
                <div class="col-12 col-lg-10">
                    <router-view></router-view>
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
  import fetchIntercept from 'fetch-intercept';

  export default {
    name: 'app',
    components: {
      Header, Footer, Menu
    },
    async created() {

      await this.$store.dispatch('security/getAuthToken', true);
      const store = this.$store;
      const router = this.$router;

      const unregister = fetchIntercept.register({
        async request(url, config) {

          const currentRoute = router.history.current;

          if (!currentRoute.meta.isAuthRequired) {
            return [url, config];
          }

          if (!config) {
            config = {};
          }

          if (!config.headers) {
            config.headers = {};
          }

          if (!url.includes('login') && !url.includes('refresh') && !url.includes('registration')) {
            config.headers['Authorization'] = 'Bearer ' + await store.dispatch('security/getAuthToken');
          }

          return [url, config];
        },
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
      });
    }
  }
</script>