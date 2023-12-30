<template>
  <b-loading v-if="isLoading" active/>
  <div v-else>

    <breadcrumb
        :root="{to: {name: 'home'}, label: 'Home'}"
        :level1="{to: {name: 'forum_index'}, label: 'Forum'}"
        :level2="{to: {name: 'forum_topic_list', params: {slug: topic.forum.slug}}, label: topic.forum.title}"
        :current="{label: topic.title}"
    />

    <h1 class="subtitle is-3">{{ topic.title }}</h1>

    <topic-post v-for="post in posts"
                :post="post"
                :key="post.id"
    />

    <b-pagination
        v-if="displayPagination"
        :total="totalItems"
        v-model="current"
        range-before="3"
        range-after="1"
        :per-page="maxItemsPerPage"
        aria-next-label="Page suivante"
        aria-previous-label="Page précédente"
        aria-page-label="Page"
        aria-current-label="Page courante"
    >
      <template #default="props">
        <b-pagination-button
            :page="props.page"
            :id="`page${props.page.number}`"
            tag="router-link"
            :to="getDefaultPage(props.page.number)">
          {{ props.page.number }}
        </b-pagination-button>
      </template>
      <template #previous="props">
        <b-pagination-button
            :page="props.page"
            tag="router-link"
            :to="getPreviousPage(props.page.number)">
          <b-icon icon="angle-left"/>
        </b-pagination-button>
      </template>

      <template #next="props">
        <b-pagination-button
            :page="props.page"
            tag="router-link"
            :to="getDefaultPage(props.page.number)">
          <b-icon icon="angle-right"/>
        </b-pagination-button>
      </template>
    </b-pagination>

    <hr/>

    <div class="columns mt-5" v-if="topic">
      <div class="column is-8 is-offset-2">
        <h4 class="subtitle is-4">Poster un message sur ce sujet</h4>

        <add-message-form :topic="topic"/>
      </div>
    </div>
  </div>
</template>

<script>
import forum from "../../../api/forum/forum";
import Breadcrumb from "../../../components/global/Breadcrumb.vue";
import TopicPost from "./TopicPost.vue";
import {FORUM_MAX_TOPIC_POST_PER_PAGE} from "../../../constants/forum";
import AddMessageForm from "./Add/AddMessageForm.vue";
import {EVENT_MESSAGE_CREATED} from "../../../constants/events";

export default {
  components: {AddMessageForm, TopicPost, Breadcrumb},
  data() {
    return {
      isLoading: true,
      topic: null,
      posts: [],
      current: 1,
      totalItems: 0,
    }
  },
  computed: {
    displayPagination() {
      return this.totalItems > FORUM_MAX_TOPIC_POST_PER_PAGE;
    },
    maxItemsPerPage() {
      return FORUM_MAX_TOPIC_POST_PER_PAGE;
    }
  },
  metaInfo() {
    return {
      title: this.pageTitle(),
    }
  },
  watch: {
    $route() {
      this.current = this.$route.params.page ? +this.$route.params.page : 1;
      this.fetchData();
    },
  },
  mounted() {
    this.$root.$on(EVENT_MESSAGE_CREATED, (data) => {
      if (this.posts.length === FORUM_MAX_TOPIC_POST_PER_PAGE) {
        // todo handle if we are not in the last page
        // go to next page
        this.$router.push({name: 'forum_topic_item', params: {slug: this.topic.slug, page: this.current + 1}})
      } else {
        this.posts.push(data.post);
      }
    });
  },
  async created() {
    this.isLoading = true;
    this.current = this.$route.params.page ? +this.$route.params.page : 1;
    const slug = this.$route.params.slug;
    this.topic = await forum.getTopic(slug);
    await this.fetchData();
    this.isLoading = false;
  },
  methods: {
    pageTitle() {
      if (this.topic) {
        return this.topic.title;
      }

      return 'Toutes les publications relatives à la musique | MusicAll'
    },
    async fetchData() {
      const metaPosts = await forum.getPostsByTopic({
        topic: this.topic.id,
        order: {'creationDatetime': 'asc'},
        page: this.current
      });
      this.posts = metaPosts['hydra:member']
      this.totalItems = metaPosts['hydra:totalItems']
    },
    getDefaultPage(pageNumber) {
      return {
        name: 'forum_topic_item',
        params: pageNumber === 1 ? {slug: this.topic.slug} : {slug: this.topic.slug, page: pageNumber}
      }
    },
    getPreviousPage(pageNumber) {
      return {
        name: 'forum_topic_item',
        params: pageNumber <= 1 ? {slug: this.topic.slug} : {slug: this.topic.slug, page: pageNumber}
      }
    }
  }
}
</script>