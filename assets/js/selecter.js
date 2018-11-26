import $ from 'jquery';
import select2 from 'select2';

select2($);

exports.init = function() {
  $('.styled-select').select2();

  $('#statut').change(function() {
    if ($('option:selected', $(this)).val() == 2) {
      $('#recherche_musicien_input').slideDown();
      $('#recherche_musicien_input').find('select').trigger('change');
    }
    else {
      $('#recherche_musicien_input').slideUp();
    }
  });
};