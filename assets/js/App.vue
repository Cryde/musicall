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
    created () {
      let isAuthenticated = JSON.parse(this.$parent.$el.attributes["data-is-authenticated"].value);
      let user = JSON.parse(this.$parent.$el.attributes["data-user"].value);
      this.$store.dispatch("security/refresh", {isAuthenticated, user});

      const unregister = fetchIntercept.register({
        response: function (response) {
          // Modify the reponse object
          return response;
        },
      });
/*
      axios.interceptors.response.use(undefined, (err) => {
        return new Promise(() => {
          if (err.response.status === 403) {
            this.$router.push({path: '/login'})
          } else if (err.response.status === 500) {
            document.open();
            document.write(err.response.data);
            document.close();
          }
          throw err;
        });
      });
 */
    }
  }
</script>