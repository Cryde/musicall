import $ from 'jquery';

export default () => {
  $('.js-are-you-sure').on('click', function(e) {
    const title = $(this).data('title') || 'Êtes vous sur ?';
    if (!confirm(title)) {
      e.preventDefault();
    }
  });
}