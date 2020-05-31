<template>
    <b-row no-gutters>
        <b-col cols="12" id="thread-list-container" v-if="threads.length">
            <b-row v-for="thread in orderedThreads" :key="thread.thread.id" class="thread-item w-100 p-2"
                   :class="{'is-new': !thread.meta.is_read, 'current': currentThreadId === thread.thread.id}"
                   @click="selectCurrentThread(thread.thread)"
            >
                <b-avatar :text="participantWithoutCurrentUser(thread.participants) | firstLetter"
                          class="mr-2 ml-2 thread-avatar"></b-avatar>

                <div class="d-inline-block" style="width: 75%">
                    <span class="d-block thread-item-username">{{ participantWithoutCurrentUser(thread.participants) }}</span>
                    <span class="d-block thread-item-date">{{ thread.thread.last_message.creation_datetime | relativeDate }}</span>
                    <span class="d-block thread-item-last-message">{{ thread.thread.last_message.content }}</span>
                </div>
            </b-row>
        </b-col>
        <b-col cols="12" class="text-center thread-list-container pr-2 no-thread pt-4">
            Vous n'avez pas encore de message.
        </b-col>
    </b-row>
</template>

<script>
  import {mapGetters} from 'vuex';

  export default {
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
      async selectCurrentThread(thread) {
        await this.$store.dispatch('messages/loadThread', {threadId: thread.id});
      },
      participantWithoutCurrentUser(participants) {
        const results = participants.filter(p => this.user.username !== p.user.username).map(item => item.user.username);

        if (results.length === 1) {
          return results[0];
        }

        return results.join(', ');
      }
    }
  }
</script>