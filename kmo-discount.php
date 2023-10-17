<?php
/*
Plugin Name: KMO Discount
Description: KMO discount functionality.
Version: 1.0
*/

// Adjust cart total amount
add_filter( 'woocommerce_cart_get_total', 'filter_cart_get_total', 10, 1 );
function filter_cart_get_total( $total ) {
    $tax_amount = 0;

    foreach( WC()->cart->get_fees() as $fee ) {
        if( ! $fee->taxable && $fee->tax < 0 ) {
            $tax_amount -= $fee->tax;
        }
    }

    if( $tax_amount != 0 ) {
        $total += $tax_amount;
    }
    return $total;
}

// Adjust Fee taxes (array of tax totals)
add_filter( 'woocommerce_cart_get_fee_taxes', 'filter_cart_get_fee_taxes', 10, 1 );
function filter_cart_get_fee_taxes( $fee_taxes ) {
    $fee_taxes = array();
    
    foreach( WC()->cart->get_fees() as $fee ) {
        if( $fee->taxable ) {
            foreach( $fee->tax_data as $tax_key => $tax_amount ) {
                if( isset($fee_taxes[$tax_key]) ) {
                    $fee_taxes[$tax_key] += $tax_amount;
                } else {
                    $fee_taxes[$tax_key] = $tax_amount;
                }
            }
        }
    }
    return $fee_taxes;
}

// Displayed fees: Remove taxes from non taxable fees with negative amount
add_filter( 'woocommerce_cart_totals_fee_html', 'filter_cart_totals_fee_html', 10, 2 );
function filter_cart_totals_fee_html( $fee_html, $fee ) {
    if( ! $fee->taxable && $fee->tax < 0 ) {
        return wc_price( $fee->total );
    }
    return $fee_html;
}

// Adjust Order fee item(s) for negative non taxable fees
add_action( 'woocommerce_checkout_create_order_fee_item', 'alter_checkout_create_order_fee_item', 10, 4 );
function alter_checkout_create_order_fee_item( $item, $fee_key, $fee, $order ) {
    if ( ! $fee->taxable && $fee->tax < 0 ) {
        $item->set_taxes(['total' => []]);
        $item->set_total_tax(0);
    }
}



add_action( 'woocommerce_cart_calculate_fees', 'add_discount_without_tax' );
function add_discount_without_tax( $cart ) { 
    if ( is_admin() && ! defined( 'DOING_AJAX' ) )
        return;

    $discount_percentage = 100000000;

    // Iterate through cart items.
    foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
        $product_id = $cart_item['product_id'];

        if($cart_item['flexible_product_fields']) {
    		foreach ($cart_item['flexible_product_fields'] as $sme_key => $sme_value) {
    			if($sme_value['name']=='Ik wil gebruiken maken van KMO-portefeuille') {

                    $product_quantity = $cart_item['quantity'];
                
                    // Calculate the total discount based on the number of eligible products.
                    $discount = (($cart_item['data']->get_price()) * $discount_percentage * $product_quantity) / 100;
                    
                    // Apply the discount.
                    WC()->cart->add_fee('Nieuw subtotaal (te gebruiken in KMO-dossier)', -$discount, false);
                }
            }
        }
    }
};

// Customize the display of a specific fee amount in the cart
add_filter('woocommerce_cart_totals_fee_html', 'wc_custom_specific_fee_amount_display', 10, 2);

function wc_custom_specific_fee_amount_display($fee_html, $fee) {
    // Check if the fee name matches the specific fee you want to customize
    if ($fee->id == 'nieuw-subtotaal-te-gebruiken-in-kmo-dossier') {
        // If the fee starts with a minus sign, add a space after it
        $fee_html = str_replace('-', '', $fee_html);
    }

    return $fee_html;
}

add_action( 'woocommerce_check_cart_items', 'wc_prevent_checkout_for_KMO' );
function wc_prevent_checkout_for_KMO() {
    $kmo_product=false;
    $nonkmo_product = false;

   foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
        $checked = false;
		if($cart_item['flexible_product_fields']) {
    		foreach ($cart_item['flexible_product_fields'] as $sme_key => $sme_value) {
    			if($sme_value['name']=='Ik wil gebruiken maken van KMO-portefeuille') {
    				$kmo_product=true;
                    $checked = true;
    			}
    		}
    	}
        
        if (!$checked) $nonkmo_product = true;
    }
    
    if($kmo_product && $nonkmo_product) {
        wc_add_notice('Opgelet! Uw bestelling kan geen combinatie van normale opleidingen en opleidingen met KMO-portefeuille bevatten. Gelieve hiervoor aparte bestellingen te plaatsen.', 'error' );
        wc_print_notice('Opgelet! Uw bestelling kan geen combinatie van normale opleidingen en opleidingen met KMO-portefeuille bevatten. Gelieve hiervoor aparte bestellingen te plaatsen.', 'error' );
        
        return false;
    }
     
    return true;
}