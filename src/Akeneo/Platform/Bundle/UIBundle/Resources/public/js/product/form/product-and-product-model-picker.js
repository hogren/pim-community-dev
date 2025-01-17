'use strict';

/**
 * This extension allows user to display a fullscreen item picker.
 * It overrides the default item picker because we have to manage 2 types of entities:
 * - products (identified by their identifier)
 * - product models (identifier by their code)
 *
 * @author    Pierre Allard <pierre.allard@akeneo.com>
 * @copyright 2017 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
define([
  'jquery',
  'oro/translator',
  'pim/common/item-picker',
  'pim/fetcher-registry',
  'pim/media-url-generator',
], function ($, __, ItemPicker, FetcherRegistry, MediaUrlGenerator) {
  return ItemPicker.extend({
    /**
     * {@inheritdoc}
     */
    selectModel: function (model) {
      const item =
        model.attributes.document_type === 'product_model'
          ? `product_model;${model.get('identifier')}`
          : `product;${model.get(this.config.columnName)}`;

      this.addItem(item);
    },

    /**
     * {@inheritdoc}
     */
    unselectModel: function (model) {
      this.removeItem(`${model.attributes.document_type};${model.get(this.config.columnName)}`);
    },

    /**
     * {@inheritdoc}
     */
    updateBasket: function () {
      let productUuids = [];
      let productModelCodes = [];
      this.getItems().forEach(item => {
        const matchProductModel = item.match(/^product_model;(.*)$/);
        if (matchProductModel) {
          productModelCodes.push(matchProductModel[1]);
        } else {
          const matchProduct = item.match(/^product;(.*)$/);
          productUuids.push(matchProduct[1]);
        }
      });

      $.when(
        FetcherRegistry.getFetcher('product-model').fetchByIdentifiers(productModelCodes),
        FetcherRegistry.getFetcher('product').fetchByUuids(productUuids)
      ).then(
        function (productModels, products) {
          this.renderBasket(products.concat(productModels));
          this.delegateEvents();
        }.bind(this)
      );
    },

    /**
     * {@inheritdoc}
     */
    imagePathMethod: function (item) {
      let filePath = null;
      if (item.meta.image !== null) {
        filePath = item.meta.image.filePath;
      }

      return MediaUrlGenerator.getMediaShowUrl(filePath, 'thumbnail_small');
    },

    /**
     * {@inheritdoc}
     */
    labelMethod: function (item) {
      return item.meta.label[this.getLocale()];
    },

    /**
     * {@inheritdoc}
     */
    itemCodeMethod: function (item) {
      if (item.code) {
        return `product_model;${item.code}`;
      } else {
        return `product;${item[this.config.columnName]}`;
      }
    },
  });
});
