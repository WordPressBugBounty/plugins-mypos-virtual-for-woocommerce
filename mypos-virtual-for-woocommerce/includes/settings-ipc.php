<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings for myPOS Checkout
 */
return array(
	'enabled'                       => array(
		'title'   => __( 'Enable/Disable', 'mypos-payments' ),
		'label'   => __( 'Enable myPOS Checkout Payment', 'mypos-payments' ),
		'type'    => 'checkbox',
		'default' => 'yes',
	),
	'title'                         => array(
		'title'       => __( 'Title', 'mypos-payments' ),
		'description' => __( 'This controls the title which the user sees during checkout.', 'mypos-payments' ),
		'default'     => __( 'Card payment - myPOS', 'mypos-payments' ),
		'type'        => 'text',
		'desc_tip'    => true,
	),
	'description'                   => array(
		'title'       => __( 'Description', 'mypos-payments' ),
		'type'        => 'text',
		'desc_tip'    => true,
		'description' => __( 'This controls the description which the user sees during checkout.', 'mypos-payments' ),
		'default'     => __( 'Pay via myPOS Checkout.', 'mypos-payments' ),
	),
	'test'                          => array(
		'title'   => __( 'Test Mode', 'mypos-payments' ),
		'label'   => __( 'Enable test mode', 'mypos-payments' ),
		'type'    => 'checkbox',
		'default' => 'yes',
        'class'   => 'mypos-toggle',
	),
	'debug'                         => array(
		'title'   => __( 'Logging', 'mypos-payments' ),
		'label'   => __( 'Enable logging', 'mypos-payments' ),
		'type'    => 'checkbox',
		'default' => 'yes',
	),
	'use_deadline_for_orders'       => array(
		'label'   => __( 'Cancel my pending orders from myPOS Checkout after 24 hours', 'mypos-payments' ),
		'type'    => 'checkbox',
		'default' => 'no',
	),
	'payment_method_1'              => array(
		'title'       => __( 'Payment method', 'mypos-payments' ),
		'label'       => __( 'Card Payment', 'mypos-payments' ),
		'type'        => 'checkbox',
		'class'       => 'wc-enhanced-checkbox',
		'description' => 'You can choose which payment methods to use',
		'desc_tip'    => true,
		'default'     => 'no',
	),

	'payment_method_2'              => array(
		'label'    => __( 'iDeal', 'mypos-payments' ),
		'type'     => 'checkbox',
		'class'    => 'wc-enhanced-checkbox',
		'desc_tip' => true,
		'default'  => 'no',
	),
	'payment_method_4'              => array(
		'label'    => __( 'Satispay', 'mypos-payments' ),
		'type'     => 'checkbox',
		'class'    => 'wc-enhanced-checkbox',
		'desc_tip' => true,
		'default'  => 'no',
	),
	'payment_method_5'              => array(
		'label'    => __( 'TWINT', 'mypos-payments' ),
		'type'     => 'checkbox',
		'class'    => 'wc-enhanced-checkbox',
		'desc_tip' => true,
		'default'  => 'no',
	),
	'payment_method_6'              => array(
		'label'    => __( 'IRIS', 'mypos-payments' ),
		'type'     => 'checkbox',
		'class'    => 'wc-enhanced-checkbox',
		'desc_tip' => true,
		'default'  => 'no',
	),
	'payment_method_3'              => array(
		'label'       => __( 'All payment methods', 'mypos-payments' ),
		'type'        => 'checkbox',
		'class'       => 'wc-enhanced-checkbox',
		'description' => 'If this option is selected, all payment methods will be displayed',
		'desc_tip'    => true,
		'default'     => 'yes',
	),
	'developer_options'             => array(
		'title'       => __( 'Developer (Test) options', 'mypos-payments' ),
		'type'        => 'title',
		'description' => '',
	),
	//    'developer_payment_method' => array(
	//        'title'       => __( 'Payment Method', 'mypos-payments' ),
	//        'type'        => 'select',
	//        'class'       => 'wc-enhanced-select',
	//        'desc_tip'    => true,
	//        'default'     => 3,
	//        'options' => array(
	//            '1' => __( 'Card Payment', 'mypos-payments' ),
	//            '2' => __( 'iDeal', 'mypos-payments' ),
	//            '3' => __( 'All', 'mypos-payments' ),
	//        ),
	//    ),
		'developer_easy_setup'      => array(
			'type'        => 'title',
			'css'         => 'color: grey;',
			'description' => __( 'Easy setup', 'mypos-payments' ),
		),
	'developer_package'             => array(
		'title'       => __( 'Configuration Pack', 'mypos-payments' ),
		'type'        => 'textarea',
		'description' => sprintf(
            /* translators: %s: link to documentation */
            __( 'Paste the Developer Configuration Package from your myPOS Developer account. You can obtain test credentials from the %s. Leave empty to configure manually below.', 'mypos-payments' ),
        '<a href="https://developers.mypos.com/en/doc/online_payments/v1_4/226-test-data" target="_blank">' . __( 'myPOS Test Data documentation', 'mypos-payments' ) . '</a>'
        ),
		'desc_tip'    => false,
		'default'     => '',
		'placeholder' => __( 'Paste Developer Configuration Package here (optional)', 'mypos-payments' ),
	),
//	'developer_advanced_setup'      => array(
//		'type'        => 'title',
//		'description' => __( 'Advanced setup', 'mypos-payments' ),
//	),
//	'developer_sid'                 => array(
//		'title'       => __( 'Store ID', 'mypos-payments' ),
//		'type'        => 'hidden',
//		'description' => __( 'Store ID is given when you add a new online store. It could be reviewed in your online banking at www.mypos.com > menu Online stores.', 'mypos-payments' ),
//		'desc_tip'    => true,
//	),
//	'developer_wallet_number'       => array(
//		'title'       => __( 'Client Number', 'mypos-payments' ),
//		'type'        => 'hidden',
//		'description' => __( 'You can view your myPOS Client number in your online banking at www.mypos.com', 'mypos-payments' ),
//		'desc_tip'    => true,
//	),
//	'developer_private_key'         => array(
//		'title'       => __( 'Private Key', 'mypos-payments' ),
//		'type'        => 'hidden',
//		'description' => __( 'The Private Key for your store is generated in your online banking at www.mypos.com > menu  Online stores > Keys.', 'mypos-payments' ),
//		'desc_tip'    => true,
//	),
//	'developer_public_certificate'  => array(
//		'title'       => __( 'myPOS Public Certificate', 'mypos-payments' ),
//		'type'        => 'hidden',
//		'description' => __( 'The myPOS Public Certificate is available for download in your online banking at www.mypos.com > menu  Online stores > Keys.', 'mypos-payments' ),
//		'desc_tip'    => true,
//	),
//	    'developer_url' => array(
//	        'title'       => __( '', 'woocommerce-gateway-mypos' ),
//	        'type'        => 'hidden',
//	        'default'     => 'https://www.mypos.com/vmp/checkout-test',
//	    ),
//		'developer_keyindex'        => array(
//			'title'       => __( 'Developer Key Index', 'mypos-payments' ),
//			'type'        => 'hidden',
//			'css'         => 'margin-bottom: 100px;',
//			'description' => __( 'The Key Index assigned to the certificate could be reviewed in your online banking at www.mypos.com > menu Online stores > Keys.', 'mypos-payments' ),
//			'desc_tip'    => true,
//		),

	'production_options'            => array(
		'title'       => __( 'Production options', 'mypos-payments' ),
		'type'        => 'title',
		'description' =>  sprintf(
            __( 'Before using this payment method, you need to create and configure your online store in the myPOS system. 
First, follow the official documentation to set up your store and generate a Configuration Pack. 
You can find detailed instructions here: <a href="https://developers.mypos.com/en/doc/online_payments/v1_4/5-store-management" target="_blank" rel="noopener noreferrer">Store Management</a>. 
After generating the Configuration Pack, paste it into the field below to connect your store with this plugin. 
Additional information about the contents and configuration of the Configuration Pack can be found here: 
<a href="https://developers.mypos.com/en/doc/online_payments/v1_4/309-configuration" target="_blank" rel="noopener noreferrer">Configuration Documentation</a>.', 'mypos-payments' )

        )
	),
	//    'production_payment_method' => array(
	//        'title'       => __( 'Payment Method', 'woocommerce-gateway-mypos' ),
	//        'type'        => 'select',
	//        'class'       => 'wc-enhanced-select',
	//        'desc_tip'    => true,
	//        'default'     => 3,
	//        'options' => array(
	//            '1' => __( 'Card Payment', 'woocommerce-gateway-mypos' ),
	//            '2' => __( 'iDeal', 'woocommerce' ),
	//            '3' => __( 'All', 'woocommerce' ),
	//        ),
	//    ),
		'production_ppr'            => array(
			'title'       => __( 'Checkout form view', 'mypos-payments' ),
			'type'        => 'select',
			'class'       => 'wc-enhanced-select',
			'description' => __( '<strong>Full payment form</strong><br/>When you choose the "Full payment form", you can collect detailed customer information on checkout - customer names, address, phone number and email. Have in mind, that if your website has a shipping form, customer should double type some of the details. All fields are mandatory. Names and email address are not editable on the payment page.<br/><br/><strong>Simplified payment form</strong><br/>Similar to the "Full payment form". However, customer names and email addresses are editable on the payment page.<br/><br/><strong>Ultra-simplified payment form</strong><br/>The most basic payment form - it requires only card details. Use this only if you collect customer details on a prior page.', 'mypos-payments' ),
			'desc_tip'    => true,
			'default'     => 3,
			'options'     => array(
				'1' => __( 'Full payment form', 'mypos-payments' ),
				'2' => __( 'Simplified payment form', 'mypos-payments' ),
				'3' => __( 'Ultra-simplified payment form', 'mypos-payments' ),
			),
		),
	'production_easy_setup'         => array(
		'type'        => 'title',
		'css'         => 'color: grey;',
		'description' => __( 'Easy setup', 'mypos-payments' ),
	),
	'production_package'            => array(
		'title'       => __( 'Configuration Pack', 'mypos-payments' ),
		'type'        => 'textarea',
		'description' => __( 'The Configuration for your store is generated in your online banking at www.mypos.com > menu Online stores.', 'mypos-payments' ),
		'desc_tip'    => true,
	),
//	'production_advanced_setup'     => array(
//		'type'        => 'title',
//		'css'         => 'color: grey;',
//		'description' => __( 'Advanced setup', 'mypos-payments' ),
//	),
//	'production_sid'                => array(
//		'title'       => __( 'Store ID', 'mypos-payments' ),
//		'type'        => 'text',
//		'description' => __( 'Store ID is given when you add a new online store. It could be reviewed in your online banking at www.mypos.com > menu Online stores.', 'mypos-payments' ),
//		'desc_tip'    => true,
//	),
//	'production_wallet_number'      => array(
//		'title'       => __( 'Client Number', 'mypos-payments' ),
//		'type'        => 'text',
//		'description' => __( 'You can view your myPOS Client number in your online banking at www.mypos.com', 'mypos-payments' ),
//		'desc_tip'    => true,
//	),
//	'production_private_key'        => array(
//		'title'       => __( 'Private Key', 'mypos-payments' ),
//		'type'        => 'textarea',
//		'description' => __( 'The Private Key for your store is generated in your online banking at www.mypos.com > menu Online stores > Keys.', 'mypos-payments' ),
//		'desc_tip'    => true,
//	),
//	'production_public_certificate' => array(
//		'title'       => __( 'myPOS Public Certificate', 'mypos-payments' ),
//		'type'        => 'textarea',
//		'description' => __( 'The myPOS Public Certificate is available for download in your online banking at www.mypos.com > menu Online stores > Keys.', 'mypos-payments' ),
//		'desc_tip'    => true,
//	),
	//    'production_url' => array(
	//        'title'       => __( '', 'woocommerce-gateway-mypos' ),
	//        'type'        => 'hidden',
	//        'default'     => 'https://www.mypos.com/vmp/checkout',
	//    ),
//		'production_keyindex'       => array(
//			'title'       => __( 'Production Key Index', 'mypos-payments' ),
//			'type'        => 'text',
//			'description' => __( 'The Key Index assigned to the certificate could be reviewed in your online banking at www.mypos.com > menu Online stores > Keys.', 'mypos-payments' ),
//			'desc_tip'    => true,
//		),
//
//	'merchant_wallet_number'        => array(
//		'title'       => '', //__( 'Merchant wallet number', 'woocommerce' ),
//		'type'        => 'hidden',
//		'description' => '', //__('Merchant number for send money on order complete', 'woocommerce'),
//		'desc_tip'    => true,
//	),

	'merchant_send_money_reason'    => array(
		'title'       => '', //__( 'Merchant wallet number', 'woocommerce' ),
		'type'        => 'hidden',
		'description' => '', //__('Merchant number for send money on order complete', 'woocommerce'),
		'desc_tip'    => true,
	),
);
