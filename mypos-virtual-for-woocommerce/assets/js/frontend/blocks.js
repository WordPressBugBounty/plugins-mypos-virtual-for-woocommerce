(() => {
    "use strict";

    const { createElement, RawHTML } = window.React;
    const { decodeEntities } = window.wp.htmlEntities;
    const { getSetting } = window.wc.wcSettings;
    const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
    const { registerCheckoutBlock } = window.wc.blocksCheckout;

    const myposData = getSetting("mypos_virtual_data");

    if (!myposData) return;

    const labelText = decodeEntities(myposData.title) || "Card Payment - myPOS";
    const imgMarkup = `<img src="${myposData.path}/assets/images/card_schemes_ideal_no_bg.png" alt="Card Schemes" style="max-width: 100%;"/>`;
    const descriptionMarkup = myposData.description
        ? `${decodeEntities(myposData.description)} ${imgMarkup}`
        : `Pay via myPOS Checkout ${imgMarkup}`;

    const MyPOSLabel = ({ components: { PaymentMethodLabel } }) =>
        createElement(PaymentMethodLabel, {
            text: labelText,
        });

    const MyPOSContent = () =>
		createElement("div", {
			dangerouslySetInnerHTML: { __html: descriptionMarkup },
		});

    const myposPaymentMethod = {
        name: "mypos_virtual",
        label: createElement(MyPOSLabel),
        ariaLabel: labelText,
        canMakePayment: () => true,
        content: createElement(MyPOSContent),
        edit: createElement("div"),
        supports: myposData.supports || {},
    };

    const myposCheckoutBlock = {
        metadata: {
            name: "mypos_virtual",
            title: myposData.title,
            description: myposData.description,
            category: "woocommerce",
            parent: ["woocommerce/checkout-payment-methods-block"],
            supports: myposData.supports || {},
        },
        component: () => createElement("div", null),
        force: true,
    };

    registerPaymentMethod(myposPaymentMethod);
    registerCheckoutBlock(myposCheckoutBlock);
})();
