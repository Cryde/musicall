import axios from 'axios';

export default {
  getPublications(context) {
    return axios.post(Routing.generate('api_user_publication_list'), context)
    .then(resp => resp.data);
  },
  getPublication(id) {
    return axios.get(Routing.generate('api_user_publication_show', {id}))
    .then(resp => resp.data)
    .then(resp => resp.data.publication);
  },
  publishPublicationApi(id) {
    return axios.get(Routing.generate('api_user_publication_publish', {id}))
    .then(resp => resp.data);
  },
  deleteItem(id) {
    return axios.delete(Routing.generate('api_user_publication_delete', {id}))
  },
  addPublication({title, categoryId}) {
    return axios.post(Routing.generate('api_user_publication_add'), {title, category_id: categoryId})
    .then(resp => resp.data)
    .then(resp => resp.data.publication);
  },
  savePublication({id, data}) {
    return axios.post(Routing.generate('api_user_publication_save', {id}), data)
    .then(resp => resp.data)
    .then(resp => resp.data.publication);
  }
}