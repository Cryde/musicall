import $ from "jquery";
import "./plugins/modal";

exports.init = function () {

  const body = $('body');

  body.on('submit', '#uploader-form', function (e) {
    $('.taller, .larger').remove();
    $('.member-files-container .loader').show();
    const that = $(this);
    //const form = $(this).serialize();
    const formXhr = $.ajax({
      url: 'ajax/?controller=uploader&action=add',
      type: 'POST',
      data: new FormData(this),
      processData: false,
      contentType: false
    });
    formXhr.done(function () {
      that.trigger("reset");
      $('.member-files-container').trigger('load-file', true);
    });
    e.preventDefault();
  });

//
  body.on('load-file', '.member-files-container', function (elm, isNew) {
    const xhr = $.ajax({
        url: 'ajax/?controller=uploader&action=getall',
        dataType: 'json'
      }),
      that = $(this);
    xhr.done(function (data) {
      $('.loader', that).hide();
      $.each(data, function (i, val) {
        let cssClass = 'taller';
        if (val.width > val.height) {
          cssClass = "larger";
        }
        const div = $('<div class="' + cssClass + '"><img src="' + val.src + '" data-file-id="' + val.id + '" /></div>');
        div.data('file-infos', val);
        that.append(div);
      });
      $.modal.resize();
      if (isNew) {
        // on autoclick si il est nouveau (le fichier)
        $('div:first', that).click();
      }
    });
  });

  /* Lorsqu'on clique sur une image */
  body.on('click', '.member-files-container div', function () {
    const fileData = $(this).data('file-infos'),
      imageSummary = $('.file-summary');

    $('.upload-summary').hide();

    imageSummary.find('.file-infos').append('<img src="' + fileData.src + '" />');
    imageSummary.find('.file-infos input').val(fileData.src).on('click', function () {
      $(this).select();
    });
    imageSummary.show();
    $.modal.resize();
  });

  body.on('click', '.back-to-upload-summary', function (e) {
    e.preventDefault();
    const imageSummary = $('.file-summary');
    $('.upload-summary').show();
    imageSummary.hide();
    imageSummary.find('.file-infos img').remove();
    $.modal.resize();
  });


  $('#uploader').click(function (e) {
    e.preventDefault();
    const modal = $(this)
      .modal({
        modalClass: "modal modal-uploader",
        closeText: ''
      });
    modal.on($.modal.AJAX_COMPLETE, function () {
      $('.member-files-container').trigger('load-file');
    });
  });
};

