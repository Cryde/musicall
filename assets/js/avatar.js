import $ from "jquery";
import Draggable from "draggable";
import "./plugins/modal";

exports.init = function () {

  // Gestion du crop
  $('.crop-image-action').click(function (e) {
    e.preventDefault();
    // On init la taille de limage
    const cropImageNoDisplayDOM = $('.crop-image-no-displayed').get(0),
      imgHeight = cropImageNoDisplayDOM.naturalHeight,
      imgWidth = cropImageNoDisplayDOM.naturalWidth,
      maxRange = (imgHeight > imgWidth) ? imgWidth : imgHeight,
      zoneWidth = $('.crop-zone').width();
    $('.crop-image')
      .css({'width': imgWidth + 'px', 'height': imgHeight + 'px'});


    // On init min et max pour le range
    $('.zoom_box .slider')
      .attr('max', maxRange)
      .attr('min', zoneWidth)
      .attr('value', zoneWidth)
      .change();
  });


  // Lorsqu'on bouge le slider
  $('.zoom_box .slider').on('change', function () {

    const cropImageNoDisplayDOM = $('.crop-image-no-displayed').get(0),
      imgHeight = cropImageNoDisplayDOM.naturalHeight,
      imgWidth = cropImageNoDisplayDOM.naturalWidth,
      cropZoneElement = $('.crop-zone'),
      zoneHeight = cropZoneElement.height(),
      zoneWidth = cropZoneElement.width(),
      valueSlider = $(this).val(),
      size = (imgWidth < imgHeight) ? imgWidth : imgHeight,
      ratio = size / valueSlider,
      newHeight = Math.ceil(imgHeight / ratio),
      newWidth = Math.ceil(imgWidth / ratio),
      limitWidth = -(newWidth - zoneWidth),
      limitHeight = -(newHeight - zoneHeight);

    const domImage = $('.crop-image').css({
      'width': newWidth + 'px',
      'height': newHeight + 'px',
      'left': 0,
      'top': 0
    }).get(0);

    new Draggable(domImage, {
      limit: {
        x: [limitWidth, 0],
        y: [limitHeight, 0]
      }
    });
  });

  $('.crop-save').click(function (e) {
    const cropImageElement = $('.crop-image'),
      top = parseInt(cropImageElement.css('top'), 10),
      left = parseInt(cropImageElement.css('left'), 10),
      height = cropImageElement.height(),
      width = cropImageElement.width();

    $.ajax({
      url: '/ajax/?controller=profil&action=crop_avatar',
      type: 'POST',
      data: {'top': top, 'left': left, 'height': height, 'width': width}
    })
      .done(function () {
        window.location.reload();
      });
    e.preventDefault();
  });


  // Upload automatique de l'image
  $('.change_profil_image').click(function (e) {
    $('#change_profil_image').click();
    e.preventDefault();
  });

  $('#change_profil_image').on('change', function () {
    $('.change_profil_image').text('Chargement en cours ...');
    $(this).parents('form').submit();
  });
};