<template>
  <div class="modal-card">
    <header class="modal-card-head">
      <p class="modal-card-title">Ajouter un nouveau sujet</p>
      <button type="button" class="delete" @click="$emit('close')"/>
    </header>

    <section class="modal-card-body">
      <div v-if="!isSent">
        <b-field label="Le titre de votre sujet">
          <b-input v-model="title" placeholder="Votre titre ici"/>

        </b-field>

        <message-editor @content-update="onUpdateContent"/>
      </div>
      <div v-else class="mt-5 mb-5 has-text-centered">
        <i class="fas fa-check fa-5x has-text-success  mb-3"></i><br/>
        Le sujet a été créé !<br/>
      </div>
    </section>

    <footer class="modal-card-foot">
      <b-button type="is-light" @click="$emit('close')" v-if="!isSent">Annuler</b-button>
      <b-button type="is-light" @click="$emit('close')" v-if="isSent">Fermer</b-button>

      <b-button
          v-if="!isSent"
          type="is-success"
          @click="submit"
          :disabled="!canSubmit"
          icon-left="paper-plane">
        Créer
      </b-button>

      <b-button
          v-if="isSent"
          type="is-info"
          icon-left="eye"
          @click="go()"
      >
        Aller sur le sujet
      </b-button>
    </footer>
  </div>
</template>

<script>

import MessageEditor from "./MessageEditor";
import forum from "../../../../api/forum/forum";
import {EVENT_TOPIC_CREATED} from "../../../../constants/events";

export default {
  components: {MessageEditor},
  props: {
    forumSlug: {
      type: String,
      required: true,
    }
  },
  data() {
    return {
      isSent: false,
      isSending: false,
      title: '',
      contentHTML: '',
      contentText: '',
      topicSlug: '',
    }
  },
  computed: {
    canSubmit() {
      return this.title.trim().length > 3 && this.contentText.trim().length > 10;
    }
  },
  methods: {
    async submit() {
      this.isSent = false;
      this.isSending = true;
      try {
        const topic = await forum.postTopicMessage({
          forum: `/api/forums/${this.forumSlug}`,
          title: this.title,
          message: this.contentHTML
        });
        this.topicSlug = topic.slug;
        this.isSent = true;
        this.$root.$emit(EVENT_TOPIC_CREATED);
      } catch (e) {
        this.isSent = false;
      }
      this.isSending = false;
    },
    go() {
      this.$router.push({name: 'forum_topic_item', params: {slug: this.topicSlug}})
      this.$emit('close');
    },
    onUpdateContent(content) {
      this.contentHTML = content.html;
      this.contentText = content.text;
    }
  }
}
</script>