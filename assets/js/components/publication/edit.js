import Vue from 'vue';
import Edit from '../publication/user/Edit';
import BootstrapVue from 'bootstrap-vue'

Vue.use(BootstrapVue)

new Vue({
  el: '#publications-edit',
  template: '<Edit/>',
  components: {Edit}
});