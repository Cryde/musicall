<template>
    <div v-if="isLoading" class="text-center pt-5">
        <b-spinner variant="primary" label="Spinning"></b-spinner>
    </div>
    <div v-else-if="publication.type === 'text'">
        <h1>{{ publication.title }}</h1>

        <div class="author">Rédigé par <strong>{{publication.author.username}}</strong> le {{
            publication.publication_datetime | dateFormat }}
        </div>
        <div class="content-box p-lg-3 mt-lg-4 mt-3" v-html="publication.content"></div>
    </div>
    <div v-else-if="publication.type === 'video'">
        <h1>{{ publication.title }}</h1>
        <div class="author">Publié par <strong>{{publication.author.username}}</strong> le {{
            publication.publication_datetime | dateFormat }}
        </div>
        <b-embed
                type="iframe"
                aspect="16by9"
                class="mt-lg-4 mt-3"
                :src="`https://www.youtube.com/embed/${publication.content}`"
                allowfullscreen
        ></b-embed>
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
        'hasErrors'
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