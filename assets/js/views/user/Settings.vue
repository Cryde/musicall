<template>
  <div class="columns">
    <div class="column is-12">
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
