import $ from 'jquery';
import loader from './waiting-load';
import {handleGoogleSearch, resetLngLat} from './map';

export {init};

function init() {
  if($('#search-musician').length) {
    handleGoogleSearch();
    handleSearchClick();
  }
}

function handleSearchClick() {

  const $searchMusician = $('#search-musician');

  if($searchMusician.length) {
    $(document).on("keypress", "#search-musician-form", function (event) {
      return event.keyCode != 13;
    });
  }

  $searchMusician.click(function (e) {
    e.preventDefault();

    if($('#location').val().trim() === '') {
      resetLngLat();
    }

    const form = $(this).parents('form'),
        resultContainer = $('#results-container');

    resultContainer.html(loader);

    const getResult = $.ajax({
      url: '/ajax/?controller=search&action=get_results',
      type: 'post',
      data: form.serialize()
    });

    getResult
    .done(function (response) {
      resultContainer.html(response);
    });
  });
}


