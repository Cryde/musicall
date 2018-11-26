import $ from "jquery";

export default function () {
  onHash();
  initTooltip();
  window.addEventListener("hashchange", onHash, true);
}

function onHash() {
  if (hashContains('info')) {
    $('.highlight').removeClass('highlight');
    setTimeout(() => $('.list-flash-info').find('.content-box').addClass('highlight'), 300);
  }

  if (hashContains('dernieres-publications')) {
    $('.highlight').removeClass('highlight');
    setTimeout(() => $('.last-articles').find('.content-box').addClass('highlight'), 300);
  }
}

function hashContains(str) {
  return window.location.hash.includes(str);
}

function initTooltip() {
  new Tippy('.news-date', {
    arrow: true
  });
}