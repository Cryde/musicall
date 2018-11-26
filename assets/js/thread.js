const $ = require('jquery');

const threadContainerSelector = '.thread-container',
  addMessageSelector = '.add-thread-message';


function loadSpinner() {
  $(threadContainerSelector).append('<div class="spinner">LOADING ...</div>');
}

function removeSpinner() {
  $('.spinner', $(threadContainerSelector)).remove();
}

function loadComments() {
  const threadId = $(threadContainerSelector).data('thread-id');
  $.ajax({
    url: '/ajax/?controller=comment&action=get_comments',
    type: 'GET',
    data: {
      thread_id: threadId
    }
  })
    .done(function (comments) {
      removeSpinner();
      $(threadContainerSelector).append(comments);
    })
    .fail(function () {
      $(threadContainerSelector).append('An error occur');
    });
}

function catchAddComment() {

  $(addMessageSelector).on('click', function (e) {
    const textarea = $(this).parents('form').find('textarea'),
      message = textarea.val(),
      threadId = $(threadContainerSelector).data('thread-id');
    if ($.trim(message).length > 0) {
      textarea.css('height', '').val('');
      $.ajax({
        url: '/ajax/?controller=comment&action=add_comment',
        type: 'POST',
        data: {
          thread_id: threadId,
          message: message
        }
      })
        .done(function (content) {
          $('p.nocomments', $(threadContainerSelector)).remove();
          $(threadContainerSelector).append(content);
        })
        .fail(function () {
          textarea.val(message);
          alert('Une erreur s\'est produite');
        });
    }
    e.preventDefault();
  });
}

exports.init = function () {
  if ($(threadContainerSelector).length > 0) {
    // On commence !
    loadSpinner();
    loadComments();
    catchAddComment();
  }
};