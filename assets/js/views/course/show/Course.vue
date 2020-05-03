<template>
    <div v-if="isLoading" class="text-center pt-5">
        <b-spinner variant="primary" label="Spinning"></b-spinner>
    </div>
    <div v-else>
        <h1>{{ publication.title }}</h1>

        <div class="author">Rédigé par <strong>{{publication.author.username}}</strong> le {{
            publication.publication_datetime | dateFormat }}
        </div>
        <div class="content-box p-3 p-lg-3 mt-lg-4 mt-3 publication-container" v-html="publication.content"></div>
    </div>
</template>

<script>
  import {mapGetters} from 'vuex';
  import {format, parseISO} from 'date-fns';

  export default {
    metaInfo() {
      return {
        title: this.publication.title,
        meta: [
          {vmid: 'description', name: 'description', content: this.publication.description}
        ]
      }
    },
    async created() {
      await this.$store.dispatch('publication/getPublication', {slug: this.$route.params.slug});
    },
    computed: {
      ...mapGetters('publication', [
        'isLoading',
        'publication',
        'hasError'
      ])
    },
    filters: {
      dateFormat(date) {
        return format(parseISO(date), 'dd/MM/yyyy HH:mm');
      }
    }
  }
</script>

<style scoped>
    .author {
        font-size: 0.8em;
    }
</style>