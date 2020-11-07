<template>
  <div class="columns">
    <div class="column is-12">
      <breadcrumb
          :root="{to: {name: 'home'}, label: 'Home'}"
          :level1="{to: {name: 'user_settings'}, label: 'Paramètres'}"
          :current="{label: currentLabel}"/>

      <div class="tabs">
        <ul>
          <router-link :to="{name: 'user_settings'}" exact-active-class="is-active" tag="li">
            <a>Général</a>
          </router-link>
          <router-link :to="{name: 'user_settings_password'}" exact-active-class="is-active" tag="li">
            <a>Changer le mot de passe</a>
          </router-link>
        </ul>
      </div>

      <router-view></router-view>
    </div>
  </div>
</template>

<script>
import Breadcrumb from "../../components/global/Breadcrumb";

export default {
  components: {Breadcrumb},
  computed: {
    currentLabel() {
      switch (this.$route.name) {
        case 'user_settings' :
          return 'Paramètres généraux du compte';
        case 'user_settings_password':
          return 'Changer son mot de passe';
      }
    }
  }
}
</script>