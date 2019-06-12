import Vue from 'vue';
import List from '../publication/user/List';
import BootstrapVue from 'bootstrap-vue'

Vue.use(BootstrapVue)

new Vue({
  el: '#publications',
  template: '<List/>',
  components: {List}
});