import $ from "jquery";

export default function () {

  const $videoDescription = $('#video-description'),
    videoDescriptionContentHeight = $videoDescription.find('p').height(),
    videoDescriptionContainerHeight = $videoDescription.height();

  if (videoDescriptionContentHeight > videoDescriptionContainerHeight) {

    $videoDescription
      .find('.show-more')
      .show()
      .click(function () {

        const $this = $(this);

        if ($this.hasClass('expanded')) {
          $this.removeClass('expanded');
          $this.text('Voir plus +');
          $videoDescription.removeAttr('style');
        } else {
          $this.addClass('expanded');
          const showMoreHeight = $this.outerHeight(true);
          $videoDescription.css({
            minHeight: ((showMoreHeight * 2) + videoDescriptionContentHeight) + 'px'
          });
          $(this).text('Voir moins -');
        }
      });
  }
}