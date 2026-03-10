/**
 * @file
 * Loads recently read products from localStorage via AJAX.
 */

(function (Drupal, once) {

  'use strict';

  Drupal.behaviors.recentlyReadView = {
    attach: function (context) {
      once('recently-read-view', '[data-recently-read-view]', context).forEach(function (container) {
        if (!window.localStorage) {
          return;
        }

        var excludeId = container.dataset.recentlyReadExclude || '0';
        var key = 'recently_read';
        var items = [];

        try {
          items = JSON.parse(localStorage.getItem(key)) || [];
        }
        catch (e) {
          return;
        }

        // Filter out excluded ID and get top 3.
        var ids = items
          .filter(function (item) {
            return String(item.id) !== String(excludeId);
          })
          .slice(0, 3)
          .map(function (item) {
            return item.id;
          });

        if (!ids.length) {
          return;
        }

        fetch(Drupal.url('ajax/recently-read/products/' + ids.join(',')))
          .then(function (response) { return response.json(); })
          .then(function (data) {
            if (data.html) {
              container.innerHTML = data.html;
              container.classList.add('is-loaded');
              Drupal.attachBehaviors(container);
            }
          });
      });
    }
  };

})(Drupal, once);
