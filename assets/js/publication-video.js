import $ from "jquery";
import loader from "./waiting-load";
import autosize from "autosize";
import Draggable from "draggable";
import {isValid} from "./publication/video/validator";

let lastVideoUrl = '';
const previewVideoContainer = $('.preview-video');

/**
 * @param url
 * @returns {boolean}
 */
function isUrl(url) {
  const regexp = /^((https?)?:\/\/)?(([0-9a-z_!~*'().&=+$%-]+: )?[0-9a-z_!~*'().&=+$%-]+@)?(([0-9]{1,3}\.){3}[0-9]{1,3}|([0-9a-z_!~*'()-]+\.)*([0-9a-z][0-9a-z-]{0,61})?[0-9a-z]\.[a-z]{2,6}|localhost)(:[0-9]{1,4})?((\/?)|(\/[0-9a-z_!~*'().;?:@&=+$,%#-]+)+\/?)$/i;
  return regexp.test(url);
}

/**
 *
 * @param urlVideo
 * @returns jQueryAjax
 */
function getYoutubeVideoThumb(urlVideo) {
  return $.ajax({
    url: '/ajax/?controller=publication&action=get_video_thumb',
    data: {urlVideo: urlVideo, type: "youtube"},
    type: 'POST'
  });
}

/**
 * Once we put the url into the form
 */
function handleUrlDroping() {
  const url = $.trim($('input#url').val());
  if (url && isUrl(url) && lastVideoUrl !== url) {
    previewVideoContainer.append(loader).show();
    lastVideoUrl = url;
    getYoutubeVideoThumb(url)
      .then(loadReponseVideoData);
  }
}

/**
 *
 * @param response
 */
function loadReponseVideoData(response) {
  if (response.status) {
    handleSuccessDataLoading(response.data);
  } else {
    handleErrorDataLoading(response.error);
  }
}

/**
 *
 * @param data
 */
function handleSuccessDataLoading(data) {
  const $inputField = $('input#title'),
    $descriptionField = $('textarea#description');

  previewVideoContainer.empty().append(loader).show();

  if ($.trim($inputField.val()) === '') {
    $inputField.val(data.title);
  }

  if ($.trim($descriptionField.val()) === '') {
    $descriptionField.val(data.description);
    autosize.update($descriptionField);
  }

  // container need some resizing
  const containerWidth = previewVideoContainer.width();
  previewVideoContainer.height(containerWidth + 'px')

  // Load the image here.
  const imageContainer = $('<div class="image" />'),
    image = $('<img />');
  imageContainer.append(image);

  const domImage = image.attr('src', data.image).get(0);

  image.on('load', function () {
    const width = $(this).width(),
      margin = (width - containerWidth);

    const draggable = new Draggable(domImage, {
      limit: {
        x: [-margin, 0],
        y: [0, 0]
      },
      onDrag: onDragImage
    });
    onDragImage(domImage, 0);

    // When we double click on the image we center this one
    image.on('dblclick', function () {
      const middle = -(margin / 2);
      onDragImage(domImage, middle);
      draggable.set(middle, 0);
    });
  });

  previewVideoContainer.empty().append(imageContainer);
}

/**
 *
 * @param error
 */
function handleErrorDataLoading(error) {
  alert(error);
}

/**
 *
 * @param element
 * @param x
 */
function onDragImage(element, x) {
  const data = {
    width: $(element).width(),
    containerWidth: $(element).parent().width(),
    x: x
  };
  $('input[name="imagepreviewdata"]').val(JSON.stringify(data));
}

function validate(event) {
  event.preventDefault();
  const thatButton = $(this);
  thatButton.prop('disabled', 'disabled');

  const publication = {
      title: $('input#title').val(),
      description: $('textarea#description').val(),
      content: $('input#url').val(),
      icon: $('.preview-video img').attr('src'),
      imagepreviewdata: $('#imagepreviewdata').val()
    },
    alertErrorElement = $('.alert.publication');

  alertErrorElement.hide();

  isValid(publication)
    .then(function () {
      thatButton.removeAttr('disabled');
      thatButton.off('click').click();
    })
    .fail(function (e) {
      thatButton.trigger('stop:working');
      thatButton.removeAttr('disabled');
      alertErrorElement.show().text(e);
    });
}

exports.init = function () {
  if ($('.publication.video').length) {
    $('input#url').on('keyup', handleUrlDroping);
    $('#publish').on('click', validate);
  }
};