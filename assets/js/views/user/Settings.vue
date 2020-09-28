<template>
  <b-row>
    <b-col xl="12">
      <b-nav pills class="rounded">
        <b-nav-item :to="{name: 'user_settings'}" class="mb-3 rounded">Général</b-nav-item>
        <b-nav-item :to="{name: 'user_settings_password'}" class="mb-3 rounded">Changer le mot de passe</b-nav-item>
        <b-nav-item class="rounded">Photo de profile</b-nav-item>
      </b-nav>

      <router-view></router-view>
    </b-col>
  </b-row>
</template>

<style scoped>
/* Used by vue-router */
.router-link-exact-active {
  background:#5B87AE;
  color: white;
}
</style>