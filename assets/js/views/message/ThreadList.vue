<template>
  <div class="columns">
    <div class="column is-12" id="thread-list-container" v-if="threads.length">
      <perfect-scrollbar>
        <div class="card is-shadowless is-clickable is-radiusless is-thread-container"
             v-for="thread in orderedThreads" :key="thread.thread.id"
             :class="{'has-background-info-light': !thread.is_read, 'has-background-light': currentThreadId === thread.thread.id}"
             @click="selectCurrentThread(thread)"
        >
          <div class="card-content">
            <div class="columns">
              <div class="column">
                <avatar :user="participantWithoutCurrentUser(thread.thread.message_participants)"
                        size="32"
                        class="thread-avatar"/>
              </div>
              <div class="column">
                <small>{{ participantWithoutCurrentUser(thread.thread.message_participants).username }} <b-tag v-if="!thread.is_read" type="is-info">new</b-tag></small>
                <span class="msg-timestamp is-block">{{
                    thread.thread.last_message.creation_datetime | relativeDate
                  }}</span>
                <p class="msg-snippet truncate">
                  {{ thread.thread.last_message.content }}
                </p>
              </div>
            </div>
          </div>
        </div>
      </perfect-scrollbar>
    </div>
    <div v-else class="column is-12 has-text-centered thread-list-container pr-2 no-thread pt-4">
      Vous n'avez pas encore de message.
    </div>
  </div>
</template>

<script>
import {mapGetters} from 'vuex';
import Avatar from "../../components/user/Avatar";

export default {
  components: {Avatar},
  computed: {
    ...mapGetters('messages', ['threads', 'currentThreadId']),
    ...mapGetters('security', ['user']),
    orderedThreads() {
      return [...this.threads].sort((thread1, thread2) => {

        const date1 = thread1.thread.last_message.creation_datetime;
        const date2 = thread2.thread.last_message.creation_datetime;

        return date1 < date2;
      });
    }
  },
  filters: {
    firstLetter(word) {
      return word[0].toLocaleUpperCase();
    }
  },
  methods: {
    async selectCurrentThread(meta) {
      await this.$store.dispatch('messages/loadThread', {meta});
    },
    participantWithoutCurrentUser(participants) {

      console.log()
      const results = participants.filter(p => this.user.username !== p.participant.username).map(item => item.participant);

      if (results.length === 1) {
        return results[0];
      }
    }
  }
}
</script>

<style>

.inbox-messages {
  height: 70vh;
}

#thread-list-container .ps {
  height: 70vh;
  border-right: 1px solid #ccc;
}

.inbox-messages .msg-timestamp {
  font-size: 0.8em;
  margin-top: 2px;
  color: #5D5D5D;
}

.card.active {
  background-color: #F5F5F5;
}

.truncate {
  width: 250px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
</style>