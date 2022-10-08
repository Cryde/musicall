<template>
  <div>
    <b-loading v-if="isLoading" active/>
    <div v-else-if="!hasError && publication.type_label === 'text'">

      <breadcrumb
          :root="{to: {name: 'home'}, label: 'Home'}"
          :level1="{to: {name: 'course_index'}, label: 'Cours'}"
          :level2="{to: {name: 'course_by_category', params:{slug: publication.sub_category.slug}}, label: publication.sub_category.title}"
          :current="{label: publication.title}"
      />

      <h1 class="subtitle is-3 is-uppercase">{{ publication.title }}</h1>

      <div class="author">Rédigé par <strong>{{ publication.author.username }}</strong> <span
          v-if="publication.publication_datetime">le {{
          publication.publication_datetime | dateFormat
        }}</span>
      </div>
      <div class="box content is-shadowless p-3 p-lg-3 mt-lg-4 mt-3 publication-container"
           v-html="publication.content"></div>
    </div>
    <div v-else-if="!hasError && publication.type_label === 'video'">

      <breadcrumb
          :root="{to: {name: 'home'}, label: 'Home'}"
          :level1="{to: {name: 'course_index'}, label: 'Cours'}"
          :level2="{to: {name: 'course_by_category', params:{slug: publication.sub_category.slug}}, label: publication.sub_category.title}"
          :current="{label: publication.title}"
      />


      <h1 class="subtitle is-3 is-uppercase">{{ publication.title }}</h1>
      <div class="author">Publié par <strong>{{ publication.author.username }}</strong> le {{
          publication.publication_datetime | dateFormat
        }}
      </div>
      <figure class="image is-16by9 mt-4">
        <iframe class="has-ratio" width="640" height="360"
                :src="`https://www.youtube.com/embed/${publication.content}?showinfo=0`" frameborder="0"
                allowfullscreen></iframe>
      </figure>
    </div>
    <comment v-if="!isLoading && !hasError && publication.thread.id" :thread-id="publication.thread.id"/>
  </div>
</template>

<script>
import {mapGetters} from 'vuex';
import {format, parseISO} from 'date-fns';
import Comment from "../../comment/Thread";
import Breadcrumb from "../../../components/global/Breadcrumb";

export default {
  components: {Breadcrumb, Comment},
  metaInfo() {
    return {
      title: this.publication.title,
      meta: [
        {vmid: 'description', name: 'description', content: this.publication.description},
        {property: "og:title", content: this.publication.title},
        {property: "og:description", content: this.publication.description},
      ]
    }
  },
  async created() {
    await this.$store.dispatch('publication/getPublication', {slug: this.$route.params.slug});
    if (this.hasError) {
      this.$router.replace({name: 'course_index'});
    }
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
  },
  beforeDestroy() {
    this.$store.dispatch('publication/reset');
  }
}
</script>

<style scoped>
.author {
  font-size: 0.8em;
}
</style>