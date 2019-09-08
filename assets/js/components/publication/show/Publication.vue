<template>
    <div v-if="isLoading" class="text-center pt-5">
        <b-spinner variant="primary" label="Spinning"></b-spinner>
    </div>
    <div v-else>
        <h1>{{ publication.title }}</h1>

        <div class="content-box publication" v-html="publication.content"></div>
    </div>
</template>

<script>
  import {mapGetters} from 'vuex';

  export default {
    async created() {
      console.log(this.$route.params.slug);
      await this.$store.dispatch('publication/getPublication', {slug: this.$route.params.slug});
    },
    computed: {
      ...mapGetters('publication', [
        'isLoading',
        'publication',
        'hasErrors'
      ])
    }
  }
</script>