<template>
    <div id="nav-bar-dropdown">
        <div class="ml-auto">
            <b-dropdown size="lg" variant="link" toggle-class="text-decoration-none" no-caret>
                <template slot="button-content">
                    <div class="inset">
                        <b-img-lazy src="https://via.placeholder.com/150x150" rounded="circle"/>
                    </div>
                </template>
                <b-dropdown-text><strong>{{ user.username }}</strong></b-dropdown-text>
                <b-dropdown-divider></b-dropdown-divider>
                <b-dropdown-item :to="{name: 'user_publications'}">Mes publications</b-dropdown-item>
                <b-dropdown-divider v-if="isRoleAdmin"></b-dropdown-divider>
                <b-dropdown-item v-if="isRoleAdmin" :to="{name: 'admin_publications_pending'}">Publications en attentes</b-dropdown-item>
                <b-dropdown-divider></b-dropdown-divider>
                <b-dropdown-item href="#">Paramètres</b-dropdown-item>
                <b-dropdown-item @click="logout">Logout</b-dropdown-item>
            </b-dropdown>
        </div>
    </div>
</template>

<script>
  import {mapGetters} from 'vuex';

  export default {
    data() {
      return {
        routes: {
          user_publications: '',
        }
      }
    },
    computed: {
      ...mapGetters('security', [
        'user',
        'isRoleAdmin'
      ])
    },
    methods: {
      async logout() {
        await this.$store.dispatch('security/logout');
        window.location.reload();
      }
    }
  }
</script>
