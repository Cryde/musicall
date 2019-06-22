import Vue from 'vue';
import VueRouter from 'vue-router';
import List from '../publication/user/List';
import Edit from '../publication/user/Edit';
import BootstrapVue from 'bootstrap-vue'

Vue.use(BootstrapVue);
Vue.use(VueRouter);

const routes = [
  {
    path: '',
    name: 'user_publications',
    component: List
  },
  {
    path: '/edit/:id',
    name: 'user_publications_edit',
    component: Edit
  }
];


const router = new VueRouter({
  routes
});

const app = new Vue({
  router
}).$mount('#publications')

router.push({ name: 'user_publications'});
