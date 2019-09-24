<template>
    <div v-if="isLoading" class="text-center pt-5">
        <b-spinner variant="primary" label="Spinning"></b-spinner>
    </div>
    <div v-else-if="publication.type === 'text'">
        <h1>{{ publication.title }}</h1>

        <div class="content-box publication" v-html="publication.content"></div>
    </div>
    <div v-else-if="publication.type === 'video'">
        <h1>{{ publication.title }}</h1>

        <b-embed
                type="iframe"
                aspect="16by9"
                :src="`https://www.youtube.com/embed/${publication.content}`"
                allowfullscreen
        ></b-embed>
    </div>
</template>

<script>
  import {mapGetters} from 'vuex';

  export default {
    metaInfo () {
      return {
        title: this.publication.title
      }
    },
    async created() {
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