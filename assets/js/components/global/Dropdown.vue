<template>
    <div class="ml-auto">
        <b-dropdown size="lg"  variant="link" toggle-class="text-decoration-none" no-caret>
            <template slot="button-content" >
                <div class="inset">
                    <img src="https://via.placeholder.com/150x150" class="rounded-circle">
                </div>
            </template>
            <b-dropdown-text><strong>{{ username }}</strong></b-dropdown-text>
            <b-dropdown-divider></b-dropdown-divider>
            <b-dropdown-item :href="routes.user_publications">Mes publications</b-dropdown-item>
            <b-dropdown-item href="#">Another action</b-dropdown-item>
            <b-dropdown-divider></b-dropdown-divider>
            <b-dropdown-item :href="routes.logout">Logout</b-dropdown-item>
        </b-dropdown>
    </div>
</template>

<script>
    export default {
      data() {
        return {
          username: '',
          routes: {
            user_publications: Routing.generate('user_publications'),
            logout: Routing.generate('app_logout'),
          }
        }
      },
      mounted() {
        this.username = document.querySelector('#user-data').dataset.username;
      }
    }
</script>
