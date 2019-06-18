import Vue from 'vue';
import Dropdown from './Dropdown';
import BootstrapVue from 'bootstrap-vue'

Vue.use(BootstrapVue);

const elem = document.querySelector('#nav-bar-dropdown');

if (elem) {
  new Vue({
    el: '#nav-bar-dropdown',
    template: '<Dropdown/>',
    components: {Dropdown}
  });
}