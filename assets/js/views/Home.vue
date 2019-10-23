<template>
    <div>
        <h1>Home</h1>

        <b-alert show><i class="fas fa-exclamation-circle"></i> MusicAll est cours de <strong>re-développement</strong>. <router-link :to="{ name: 'todo' }">Pour voir l'avancée c'est ici</router-link></b-alert>

        <PublicationList/>
    </div>
</template>
<script>
  import PublicationList from "../components/publication/list/PublicationList";

  export default {
    components: {PublicationList}
  }
</script>