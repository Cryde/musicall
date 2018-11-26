import $ from 'jquery';
import GoogleMapsLoader from 'google-maps';

export {handleGoogleSearch, resetLngLat};

function handleGoogleSearch() {

  GoogleMapsLoader.LIBRARIES = ['places'];
  GoogleMapsLoader.LANGUAGE = 'fr';
  GoogleMapsLoader.KEY = 'AIzaSyBsfoARa2MWlsB-1lUxwHjk6Z_4Xwcp-mQ';

  GoogleMapsLoader.load((google) => {
    const map = initMap({google, mapSelector: '#map'});
    addressAutoComplete({google, autoCompleteDomElement: $('#location').get(0),map});
  });
}


function addressAutoComplete({google, map, autoCompleteDomElement}) {

  const autocomplete = new google.maps.places.Autocomplete(autoCompleteDomElement);
  autocomplete.bindTo('bounds', map);

  const infowindow = new google.maps.InfoWindow();

  const marker = new google.maps.Marker({
    map: map,
    anchorPoint: new google.maps.Point(0, -29)
  });

  autocomplete.addListener('place_changed', function () {

    infowindow.close();
    marker.setVisible(false);

    // Get the place details from the autocomplete object.
    const place = autocomplete.getPlace();
    if (!place.geometry) {
      // User entered the name of a Place that was not suggested and
      // pressed the Enter key, or the Place Details request failed.
      window.alert("Il n'existe pas un lieu connu pour : '" + place.name + "'");
      return;
    }


    // If the place has a geometry, then present it on a map.
    if (place.geometry.viewport) {
      map.fitBounds(place.geometry.viewport);
    } else {
      map.setCenter(place.geometry.location);
      map.setZoom(17);  // Why 17? Because it looks good.
    }

    marker.setIcon(/** @type {google.maps.Icon} */({
      url: place.icon,
      size: new google.maps.Size(71, 71),
      origin: new google.maps.Point(0, 0),
      anchor: new google.maps.Point(17, 34),
      scaledSize: new google.maps.Size(35, 35)
    }));
    marker.setPosition(place.geometry.location);
    marker.setVisible(true);

    let address = '';
    if (place.address_components) {
      address = [
        (place.address_components[0] && place.address_components[0].short_name || ''),
        (place.address_components[1] && place.address_components[1].short_name || ''),
        (place.address_components[2] && place.address_components[2].short_name || '')
      ].join(' ');
    }

    infowindow.setContent('<div><strong>' + place.name + '</strong><br>' + address);
    infowindow.open(map, marker);

    $('#longitude').val(place.geometry.location.lng());
    $('#latitude').val(place.geometry.location.lat());
  });
}


function initMap({google, mapSelector}) {
  return new google.maps.Map(document.querySelector(mapSelector), {
    center: {lat: 50.8504500, lng: 4.3487800},
    zoom: 13
  });
}

function resetLngLat() {
  $('#longitude').val('');
  $('#latitude').val('');
}