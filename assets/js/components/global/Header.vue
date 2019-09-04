<template>
    <nav class="navbar fixed-top navbar-expand-md navbar-light">
        <div class="container">
            <a class="navbar-brand" href="">
                <img src="/build/images/logo.png" alt="Logo MusicAll"/>
            </a>

            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarCollapse"
                    aria-controls="navbarCollapse" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarCollapse">
                <form class="form-inline ml-auto">
                    <input class="form-control mr-sm-2" type="text" placeholder="Rechercher" aria-label="Search">
                </form>

                <!-- if user-->
                <div v-if="isAuthenticated">
                    <div id="user-data" data-username="dd"></div>
                    <div id="nav-bar-dropdown">
                        <Dropdown/>

                    </div>
                </div>
                <div v-else>
                    <!-- else : -->
                    <router-link :to="{ name: 'login' }" class="ml-auto btn btn-sm btn-registration">s'inscrire
                    </router-link>
                    <router-link :to="{ name: 'login' }" class="ml-2 btn btn-sm btn-login">se connecter</router-link>
                </div>
            </div>
        </div>
    </nav>
</template>

<script>
  import {mapGetters} from 'vuex';
  import Dropdown from './Dropdown';

  export default {
    components: { Dropdown},
    computed: {
      ...mapGetters('security', [
        'isAuthenticated'
      ])
    }
  }
</script>