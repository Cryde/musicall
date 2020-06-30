<template>
    <div>
        <b-button v-if="isAuthenticated" :to="{name: 'announce_musician_add'}"
                  variant="primary" class="float-right">
            <i class="fas fa-bullhorn"></i> Poster une annonce
        </b-button>
        <b-button v-else variant="primary" class="float-right" v-b-tooltip.hover
                  title="Vous devez être connecté pour poster une annonce">
            <i class="fas fa-bullhorn"></i> Poster une annonce
        </b-button>

        <h1>Recherche</h1>

        La recherche de musiciens sera bientôt de retour !
    </div>
</template>

<script>
  import {mapGetters} from 'vuex';

  export default {
    computed: {
      ...mapGetters('security', ['isAuthenticated', 'isRoleAdmin'])
    }
  }
</script>