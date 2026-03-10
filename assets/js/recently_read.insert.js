/**
 * @file
 * Stores recently read entity info in localStorage.
 */

(function (Drupal, drupalSettings, once) {

  'use strict';

  Drupal.behaviors.recentlyReadInsert = {
    attach: function (context) {
      once('recently-read-insert', 'body', context).forEach(function () {
        if (!window.localStorage) {
          return;
        }

        var config = drupalSettings.recently_read;
        if (!config || !config.entity_type || !config.entity_id) {
          return;
        }

        var key = 'recently_read';
        var items = [];

        try {
          items = JSON.parse(localStorage.getItem(key)) || [];
        }
        catch (e) {
          items = [];
        }

        // Remove existing entry for this entity.
        items = items.filter(function (item) {
          return !(item.type === config.entity_type && item.id === String(config.entity_id));
        });

        // Add to front.
        items.unshift({
          type: config.entity_type,
          id: String(config.entity_id),
          time: Date.now()
        });

        // Keep last 20.
        items = items.slice(0, 20);

        localStorage.setItem(key, JSON.stringify(items));
      });
    }
  };

})(Drupal, drupalSettings, once);
