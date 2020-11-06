<template>
  <b-dropdown
      position="is-bottom-left"
      append-to-body
      aria-role="menu">
    <a
        class="navbar-item"
        slot="trigger"
        role="button">

      <avatar v-if="!isLoading" :user="user" size="24"/>

      <b-icon icon="chevron-down"></b-icon>
    </a>

    <b-dropdown-item custom aria-role="menuitem">
      <strong v-if="user" class="ml-5 ">{{ user.username }}</strong>
    </b-dropdown-item>
    <hr class="dropdown-divider">
    <b-dropdown-item has-link>
      <router-link :to="{name: 'user_musician_announce'}">
        <b-icon icon="bullhorn"></b-icon>
        Mes annonces
      </router-link>
    </b-dropdown-item>
    <b-dropdown-item has-link>
      <router-link :to="{name: 'user_publications'}">
        <b-icon icon="newspaper"></b-icon>
        Mes publications
      </router-link>
    </b-dropdown-item>
    <b-dropdown-item has-link>
      <router-link :to="{name: 'user_gallery'}">
        <b-icon icon="camera-retro"></b-icon>
        Mes galeries
      </router-link>
    </b-dropdown-item>

    <hr class="dropdown-divider" aria-role="menuitem">
    <b-dropdown-item has-link>
      <router-link :to="{name: 'user_settings'}">
        <b-icon icon="cog"></b-icon>
        Paramètres
      </router-link>
    </b-dropdown-item>
    <b-dropdown-item value="logout" aria-role="menuitem" @click="logout">
      <b-icon icon="sign-out-alt"></b-icon>
      Se déconnecter
    </b-dropdown-item>
  </b-dropdown>


  <!--  <b-dropdown variant="link" right toggle-class="text-decoration-none" no-caret>
      <template slot="button-content">
        <b-avatar
            v-if="!isLoading && userPicture"
            :badge="totalCount"
            :src="userPicture"></b-avatar>
        <b-avatar
            v-if="!isLoading && !userPicture"
            :badge="totalCount"
            :text="user.username[0].toLocaleUpperCase()"></b-avatar>
      </template>
      <b-dropdown-text><strong v-if="user">{{ user.username }}</strong></b-dropdown-text>
      <b-dropdown-divider></b-dropdown-divider>
      <b-dropdown-item :to="{name: 'message_list'}">
        Mes messages
        <b-badge variant="primary" v-if="messageCount">{{ messageCount }}</b-badge>
      </b-dropdown-item>
      <b-dropdown-divider></b-dropdown-divider>
      <b-dropdown-item :to="{name: 'user_musician_announce'}">Mes annonces</b-dropdown-item>
      <b-dropdown-item :to="{name: 'user_publications'}">Mes publications</b-dropdown-item>
      <b-dropdown-item :to="{name: 'user_gallery'}">Mes galeries</b-dropdown-item>

    </b-dropdown>-->
</template>

<script>
import {mapGetters} from 'vuex';
import Avatar from "../user/Avatar";

export default {
  components: {Avatar},
  data() {
    return {
      routes: {
        user_publications: '',
      }
    }
  },
  computed: {
    ...mapGetters('security', [
      'isRoleAdmin'
    ]),
    ...mapGetters('user', ['user', 'isLoading']),
    ...mapGetters('notifications', ['messageCount', 'pendingGalleriesCount', 'pendingPublicationsCount']),
    adminCount() {
      return this.pendingGalleriesCount + this.pendingPublicationsCount;
    },
    totalCount() {
      const total = this.messageCount + this.pendingGalleriesCount + this.pendingPublicationsCount;

      if (!total) {
        return false;
      }
      return total + '';
    },
  },
  methods: {
    async logout() {
      await this.$store.dispatch('security/logout');
      window.location.reload();
    }
  }
}
</script>
