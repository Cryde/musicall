<template>
    <div>
        <b-dropdown class="float-right" text="Ajouter">
            <b-dropdown-item
                    v-for="publication_category in publication_categories"
                    v-bind:key="publication_category.id"
                    :href="publication_category.url">
                {{ publication_category.title }}
            </b-dropdown-item>
        </b-dropdown>
    </div>
</template>

<script>
  export default {
    data() {
      return {
        publication_categories: []
      }
    },
    mounted() {
      this.getCategories()
      .then((categories) => {
        this.publication_categories = categories;
      })

    },
    methods: {
      getCategories() {

        return fetch(Routing.generate('api_publication_category_list'))
        .then(resp => resp.json())
        .then((resp) => {
          console.log(resp);
          return resp
        })
        .then(resp => resp.data.categories)
        .then(this.bindUrl)
      },
      bindUrl(categories) {
        return categories.map((category) => {
          category.url = Routing.generate('publications_add', {id: category.id});
          return category
        });
      }
    }
  }
</script>