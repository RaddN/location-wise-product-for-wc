(function () {
     if (!window.wc || !window.wc.wcBlocksRegistry || !window.wp || !window.wp.element) {
         return;
     }

     var __ = window.wp.i18n && window.wp.i18n.__ ? window.wp.i18n.__ : function (text) {
         return text;
     };

     var sanitizeHTML = window.wc.sanitize && window.wc.sanitize.sanitizeHTML ? window.wc.sanitize.sanitizeHTML : function (value) {
         return value || '';
     };

     var getPaymentMethodData = window.wc.wcSettings && window.wc.wcSettings.getPaymentMethodData ? window.wc.wcSettings.getPaymentMethodData : function () {
         return {};
     };

     var methodData = getPaymentMethodData('cash_on_pickup', {});
     var title = methodData.title || __('Cash on Pickup', 'multi-location-product-and-inventory-management-pro');
     var description = methodData.description || '';
     var sanitizedDescription = sanitizeHTML(description);
     var isLocationAllowed = methodData.locationAllowed !== false;
     var RawHTML = window.wp.element.RawHTML;
     var createElement = window.wp.element.createElement;

     var LabelComponent = function (props) {
         var PaymentMethodLabel = props && props.components && props.components.PaymentMethodLabel;
         if (PaymentMethodLabel) {
             return createElement(PaymentMethodLabel, { text: title });
         }
         return createElement('span', null, title);
     };

     var DescriptionComponent = function () {
         if (RawHTML) {
             return createElement(RawHTML, { children: sanitizedDescription });
         }
         return null;
     };

     var supports = methodData.supports || [];

    var labelElement = createElement(LabelComponent);
    var contentElement = createElement(DescriptionComponent);

    window.wc.wcBlocksRegistry.registerPaymentMethod({
        name: 'cash_on_pickup',
        label: labelElement,
        content: contentElement,
        edit: contentElement,
        canMakePayment: function () {
            return !!isLocationAllowed;
        },
        ariaLabel: title,
        supports: {
            features: supports,
        },
    });
})();
