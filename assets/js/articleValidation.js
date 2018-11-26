import $ from "jquery";

exports.init = function () {

  $('.validation').on('click', function (e) {
    e.preventDefault();
    const articleId = $(this).data('article-id'),
      isAccepted = $(this).hasClass('online'),
      message = isAccepted ? 'L\'article est en ligne' : 'L\'article a été refusé',
      parentDiv = $(this).parent('div');
    if (confirm('Êtes vous sur ?')) {
      const action = isAccepted ? 'accept' : 'refuse';
      $.ajax({
        url: 'index.php?page=admin&gestion=pending&action=article_' + action,
        type: 'POST',
        data: {article_id: articleId}
      }).done(function (response) {
        const isValid = $(response).find('.validation').length > 0;
        if (isValid) {
          parentDiv.remove();
          alert(message);
        } else {
          alert('Il y a du avoir une erreur dans le processus');
        }
      });
    }
  });
};
