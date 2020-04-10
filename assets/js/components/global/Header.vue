<template>
    <b-navbar toggleable="sm" fixed="top" type="light" variant="light">
        <b-container>
            <b-navbar-brand :to="{name: 'home'}"><img src="/build/images/logo.png" alt="Logo MusicAll"/></b-navbar-brand>

            <b-nav-form class="ml-auto d-none d-md-block">
                <b-form-input class="mr-sm-2" placeholder="Search"></b-form-input>
            </b-nav-form>

            <div v-if="isLoading">
                <b-spinner small type="grow"></b-spinner>
            </div>
            <div v-else>
                <Dropdown v-if="isAuthenticated"/>
                <div v-else>
                    <router-link :to="{ name: 'user_registration' }" class="ml-auto btn btn-registration">s'inscrire
                    </router-link>
                    <router-link :to="{ name: 'login' }" class="ml-2 btn btn-login">se connecter</router-link>
                </div>
            </div>

            <b-navbar-toggle target="nav-text-collapse"></b-navbar-toggle>
        </b-container>
    </b-navbar>
</template>

<script>
  import {mapGetters} from 'vuex';
  import Dropdown from './Dropdown';

  export default {
    components: {Dropdown},
    computed: {
      ...mapGetters('security', [
        'isAuthenticated',
        'isLoading'
      ])
    }
  }
</script>