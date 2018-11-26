const $ = require('jquery');

/**
 *
 */
exports.init = function() {
  resize();
  $(window).resize(resize);
};

function resize() {
  if($('body').width() > 600) {
    autoHeightAllMessageContainer();
    autoHeightMessageContainer();
  }
}

function autoHeightAllMessageContainer() {
  const $messagesContainer = $('#messages-container');
  const messagesContainerHeight = getWindowHeight() - getNavbarHeight() -
      getContainerMarginTop() - getTitleHeight() + getFormHeight() -
      getFooterHeight();

  if ($messagesContainer.length) {
    $messagesContainer.height(messagesContainerHeight);
  }
}

function autoHeightMessageContainer() {
  const $messageContainer = $('#message-container');
  const messageContainerHeight = getWindowHeight() - getNavbarHeight() -
      getContainerMarginTop() - getTitleHeight() - getFormHeight() -
      getFooterHeight();

  if ($messageContainer.length) {
    $messageContainer.height(messageContainerHeight);
  }
}

function getWindowHeight() {
  return +$(window).outerHeight();
}

function getNavbarHeight() {
  return +$('#nav-container').find('.navbar').height();
}

function getTitleHeight() {
  const $h2 = $('h2');
  return +$h2.outerHeight() + parseInt($h2.css('margin-top'), 10) +
      parseInt($h2.css('margin-bottom'), 10);
}

function getContainerMarginTop() {
  return parseInt($('div.content').css('margin-top'), 10);
}

function getFormHeight() {
  return +$('#message-form').outerHeight();
}

function getFooterHeight() {
  return +$('footer').outerHeight();
}