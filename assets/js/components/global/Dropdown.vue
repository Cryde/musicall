<template>
    <b-dropdown variant="link" right toggle-class="text-decoration-none" no-caret>
        <template slot="button-content">
            <b-avatar :text="user.username[0].toLocaleUpperCase()"></b-avatar>
        </template>
        <b-dropdown-text><strong>{{ user.username }}</strong></b-dropdown-text>
        <b-dropdown-divider></b-dropdown-divider>
        <b-dropdown-item :to="{name: 'user_publications'}">Mes publications</b-dropdown-item>
        <b-dropdown-divider v-if="isRoleAdmin"></b-dropdown-divider>
        <b-dropdown-item v-if="isRoleAdmin" :to="{name: 'admin_dashboard'}">
            <i class="fas fa-bolt"></i> Admin
        </b-dropdown-item>
        <b-dropdown-divider></b-dropdown-divider>
        <b-dropdown-item :to="{name: 'user_settings'}">Paramètres</b-dropdown-item>
        <b-dropdown-item @click="logout">Logout</b-dropdown-item>
    </b-dropdown>
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
