import {defineStore} from 'pinia'
import {readonly, ref} from 'vue';
import musicianAnnounceApi from '../../api/announce/musician.js';

export const useMusicianAnnounceStore = defineStore('musicianAnnounce', () => {

  const lastAnnounces = ref([]);

  async function loadLastAnnounces() {
    const announcesResponse = await musicianAnnounceApi.getLastAnnounces();

    lastAnnounces.value = announcesResponse.member;
  }


  function clear() {
    lastAnnounces.value = [];
  }

  return {
    loadLastAnnounces,
    clear,
    lastAnnounces: readonly(lastAnnounces),
  }
});
