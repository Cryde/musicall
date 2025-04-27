<template>
  <b-loading v-if="isLoading" active/>
  <div v-else>
    <breadcrumb
        :root="{to: {name: 'home'}, label: 'Home'}"
        :level1="{to: {name: 'forum_index'}, label: 'Forum'}"
        :current="{label: forum.title}"
    />

    <b-button
        type="is-info"
        class="is-pulled-right"
        icon-left="plus"
        @click="openAddTopicModal"
    >
      Créer un nouveau sujet
    </b-button>

    <h1 class="subtitle is-3">{{ forum.title }}</h1>

    <div class="box content topic-box">
      <topic-list-item
          v-for="topic in topics"
          :topic="topic"
          :key="topic.id"
      />
    </div>


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
            :to="props.page.number === 1 ? '?' : `?page=${props.page.number}`">
          {{ props.page.number }}
        </b-pagination-button>
      </template>
      <template #previous="props">
        <b-pagination-button
            :page="props.page"
            tag="router-link"
            :to="props.page.number <= 1 ? '?' : `?page=${props.page.number}`">
          <b-icon icon="angle-left"/>
        </b-pagination-button>
      </template>

      <template #next="props">
        <b-pagination-button
            :page="props.page"
            tag="router-link"
            :to="`?page=${props.page.number}`">
          <b-icon icon="angle-right"/>
        </b-pagination-button>
      </template>
    </b-pagination>
  </div>
</template>

<script>
import forum from "../../../api/forum/forum";
import Breadcrumb from "../../../components/global/Breadcrumb.vue";
import TopicListItem from "./TopicListItem.vue";
import {FORUM_MAX_TOPIC_PER_PAGE} from "../../../constants/forum";
import AddTopicForm from "./Add/AddTopicForm.vue";
import {EVENT_TOPIC_CREATED} from "../../../constants/events";

export default {
  components: {TopicListItem, Breadcrumb},
  data() {
    return {
      isLoading: true,
      topics: [],
      totalItems: 0,
      forum: null,
      current: 1
    }
  },
  metaInfo() {
    return {
      title: this.pageTitle(),
    }
  },
  computed: {
    displayPagination() {
      return this.totalItems > FORUM_MAX_TOPIC_PER_PAGE;
    },
    maxItemsPerPage() {
      return FORUM_MAX_TOPIC_PER_PAGE;
    }
  },
  watch: {
    $route() {
      this.current = this.$route.query.page ? +this.$route.query.page : 1;
      this.fetchData();
    },
  },
  async created() {

    this.$root.$on(EVENT_TOPIC_CREATED, () => {
      this.fetchData();
    });

    this.isLoading = true;
    this.current = this.$route.query.page ? +this.$route.query.page : 1;
    const slug = this.$route.params.slug;
    try {
      this.forum = await forum.getForum(slug);
    } catch (e) {
      this.$buefy.toast.open({
        message: 'Une erreur est survenue lors du chargement de ce forum',
        type: 'is-danger',
        position: 'is-bottom-left',
        duration: 5000
      });
      this.$router.replace({name: 'forum_index'});

      return;
    }
    await this.fetchData();
    this.isLoading = false;
  },
  methods: {
    pageTitle() {
      if (this.forum) {
        return `${this.forum.title} - MusicAll`;
      }

      return 'Forums - MusicAll'
    },
    async fetchData() {
      const metaTopics = await forum.getTopicsByForum({
        forum: this.forum.id,
        order: {'creationDatetime': 'desc'},
        page: this.current
      });
      this.topics = metaTopics['member']
      this.totalItems = metaTopics['totalItems']
    },
    openAddTopicModal() {
      this.$buefy.modal.open({
        parent: this,
        component: AddTopicForm,
        props: {forumSlug: this.$route.params.slug},
        hasModalCard: true,
        trapFocus: true
      })
    }
  }
}
</script>