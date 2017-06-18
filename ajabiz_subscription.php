<?php
/**
 * Plugin Name: ajabiz CRM for Wordpress
 * Description: Allows easy embedding of an sucbription form, if the Divi theme is installed it switches to Divi styling. Also you can sell a free offer through WooCommerce
 * Version: 1.0.0
 * Author: Digital Ideas
 * Author URI: http://www.digitalideas.io
 * License:     GPL3
 
Ajabiz Subscription is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
any later version.
 
Ajabiz Subscription is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
 
You should have received a copy of the GNU General Public License
along with Ajabiz Subscription. If not, see https://www.gnu.org/licenses/gpl.
 
 */

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

function ajabiz_subsription_form( $atts ) {
    $content = '';
    $errorMessages = array();
	$atts = shortcode_atts(
		array(
			'hash' => '',
			'wc_product' => 0,
			'wc_plan' => 0,
			'redirect_success_page' => 0,
			'label_email' => 'E-Mail',
			'label_firstName' => 'First name',
			'label_lastName' => 'Last name',
			'label_telephone' => 'Telephone',
			'label_submit' => 'Subscribe',
			'fields' => 'firstName+lastName,telephone,email'
        ),
    $atts, 'digitalideas_ajabiz_form' );
    
    if(defined('WOOCOMMERCE_VERSION') && !empty($atts['wc_product'])) {
        $productId = intval($atts['wc_product']);
        
        if(isset($_POST['email']) && !empty($_POST['email'])) {
            // Sanitizing and escaping: https://codex.wordpress.org/Validating_Sanitizing_and_Escaping_User_Data
            if(isset($_POST['firstName'])) {
                $firstName = sanitize_text_field($_POST['firstName']);
            }
            else {
                $firstName = '';
            }
            if(isset($_POST['lastName'])) {
                $lastName = sanitize_text_field($_POST['lastName']);
            }
            else {
                $lastName = '';
            }
            if(isset($_POST['telephone'])) {
                $telephone = sanitize_text_field($_POST['telephone']);
            }
            else {
                $telephone = '';
            }
            if(isset($_POST['email'])) {
                $email = sanitize_email($_POST['email']);
            }
            
            if(filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $address = array(
                    'first_name' => $firstName,
                    'last_name'  => $lastName,
                    'email'      => $email,
                    'phone'      => $telephone
                );
                
                add_filter('pre_option_woocommerce_registration_generate_password', 'ajabiz_generate_user_password');
                $userId = wc_create_new_customer($email, $email);
                
            	if(is_wp_error($userId)) {
            		$errorMessages[] = $userId->get_error_message();
            	}
            	else {
                    $order = wc_create_order();
                    $order->add_product( get_product( '800' ), 1 );
                    $order->set_address( $address, 'billing' );
                    $order->set_address( $address, 'shipping' );
                    $order->calculate_totals();
                    $order->update_status('completed', 'Order made through ajabiz subscription form.');
                    
                    if(!empty($atts['wc_plan'])) {
                        ajabiz_woocommerce_add_membership($userId, intval($atts['wc_plan']));
                    }
                    
                	wc_set_customer_auth_cookie($userId);
                	
                    diSubscribeToAjabizForm($atts['hash'], $email, $firstName, $lastName, null, $telephone);
                    
                    if(!empty($atts['redirect_success_page'])) {
                        $pageId = intval($atts['redirect_success_page']);
                        
                        $url = get_permalink($pageId);
                        wp_redirect($url);
                        exit;
                    }
            	}
            }
            else {
                $errorMessages[] .= "Bitte geben Sie eine valide E-Mail Adresse ein";
            }
        }
        
        if(defined('ET_BUILDER_LAYOUT_POST_TYPE') && (ET_BUILDER_LAYOUT_POST_TYPE == 'et_pb_layout')) {
            $content .= ajabiz_render_form('', $atts, $_POST, $errorMessages, 'divi');
        }
        else {
            $content .= ajabiz_render_form('', $atts, $_POST, $errorMessages);
        }
    }
    else {
        if(defined('ET_BUILDER_LAYOUT_POST_TYPE') && (ET_BUILDER_LAYOUT_POST_TYPE == 'et_pb_layout')) {
            $content .= ajabiz_render_form('http://www.ajabiz.com/form/addContact/' . $atts['hash'], $atts, $_POST, $errorMessages, 'divi');
        }
        else {
            $content .= ajabiz_render_form('http://www.ajabiz.com/form/addContact/' . $atts['hash'], $atts, $_POST, $errorMessages);
        }
    }

    return $content;
}
add_shortcode('ajabiz_subsription_form', 'ajabiz_subsription_form' );

function ajabiz_woocommerce_add_membership($userId, $planId ) {
    if(function_exists('wc_memberships_create_user_membership')) {
        $args = array(
            // Enter the ID (post ID) of the plan to grant at registration
            'plan_id'	=> $planId,
            'user_id'	=> $userId,
        );
        wc_memberships_create_user_membership($args);
        
        $userMembership = wc_memberships_get_user_membership($userId, $planId);
        $userMembership->add_note('Membership access granted on ajabiz CRM subscription form.');
    }
}

function ajabiz_render_form($submitUrl, $atts, $postFields, $errorMessages = array(), $style = 'raw') {
    $content = '';
    
    $lines = explode(',', $atts['fields']);
    if($style == 'divi') {
        $content .= '<div  class="et_pb_contact et_pb_module clearfix"><form action="' . $submitUrl . '" method="post" id="createEmbedContactForm">';
    }
    else {
        $content .= '<form action="' . $submitUrl . '" method="post" id="createEmbedContactForm">';
    }
    
    if(!empty($errorMessages)) {
        if($style == 'divi') {
            $content .= '<div class="et-pb-contact-message"><ul>';
            foreach($errorMessages as $errorMessage) {
                $content .= "<li>$errorMessage</li>";
            }
            $content .= '</ul></div> ';
        }
        else {
            $content .= '<div class="error"><ul>';
            foreach($errorMessages as $errorMessage) {
                $content .= "<li>$errorMessage</li>";
            }
            $content .= '</ul></div> ';
        }
    }
    if(count($lines) >= 1) {
        foreach($lines as $line) {
            $elements = explode('+', $line);
            if(count($elements) == 2) {
                $elementFirst = trim(array_shift($elements));
                $elementLast = trim(array_shift($elements));
                
                if(isset($atts['label_' . $elementFirst]) && (isset($atts['label_' . $elementLast]))) {
                    $labelFirst = $atts['label_' . $elementFirst];
                    $labelLast = $atts['label_' . $elementLast];
                    $valueFirst = isset($postFields[$elementFirst])?esc_attr($postFields[$elementFirst]):'';
                    $valueLast = isset($postFields[$elementLast])?esc_attr($postFields[$elementLast]):'';
                    
                    if($style == 'divi') {
                        $content .= '
                            <p class="et_pb_contact_field et_pb_contact_field_half">
                                <label class="et_pb_contact_form_label" for="ajabiz_' . $elementFirst . '">' . $labelFirst . '</label>
                                <input type="text" name="' . $elementFirst . '" data-required_mark="required" placeholder="' . $labelFirst . '" onfocus="this.placeholder = \'\'" onblur="this.placeholder = \'' . $labelFirst . '\'" id="ajabiz_' . $elementFirst . '" value="' . $valueFirst . '" />
                            </p>
                            <p class="et_pb_contact_field et_pb_contact_field_half">
                                <label class="et_pb_contact_form_label" for="ajabiz_' . $elementLast . '">' . $labelLast . '</label>
                                <input type="text" name="' . $elementLast . '" placeholder="' . $labelLast . '" onfocus="this.placeholder = \'\'" onblur="this.placeholder = \'' . $labelLast . '\'" id="ajabiz_' . $elementLast . '" value="' . $valueLast . '" />
                            </p>';
                    }
                    else {
                        $valueFirst = isset($postFields[$elementFirst])?esc_attr($postFields[$elementFirst]):'';
                        $valueLast = isset($postFields[$elementLast])?esc_attr($postFields[$elementLast]):'';
                        $content .='
                            <p>
                                <label for="ajabiz_' . $elementFirst . '">' . $labelFirst . '</label>
                                <input type="text" name="email" id="ajabiz_' . $elementFirst . '" placeholder="' . $labelFirst . '" value="' . $valueFirst . '"  />
                                <label for="ajabiz_' . $elementLast . '">' . $labelLast . '</label>
                                <input type="text" name="email" id="ajabiz_' . $elementLast . '" placeholder="' . $labelLast . '" value="' . $valueLast . '"  />
                            </p>';
                    }
                }
                else {
                    return 'Please use only defined elements, ' . esc_html($$elementFirst) . ' or ' . esc_html($elementLast) . ' isn\'t one.';
                }
            }
            else if(count($elements) == 1) {
                $element = trim(array_shift($elements));
                if(isset($atts['label_' . $element])) {
                    $label = $atts['label_' . $element];
                    $value = isset($postFields[$element])?esc_attr($postFields[$element]):'';
                    
                    if($style == 'divi') {
                        $content .= '
                            <p class="et_pb_contact_field et_pb_contact_field_last">
                                <label class="et_pb_contact_form_label" for="ajabiz_' . $element . '">' . $label . '</label>
                                <input type="text" name="' . $element . '" placeholder="' . $label . '" onfocus="this.placeholder = \'\'" onblur="this.placeholder = \'' . $label . '\'" id="ajabiz_' . $element . '" value="' . $value . '" />
                            </p>';
                    }
                    else {
                        $content .='
                            <p>
                                <label for="ajabiz_' . $element . '">' . $label . '</label>
                                <input type="text" name="email" id="ajabiz_' . $element . '" placeholder="' . $label . '" value="' . $value . '"  />
                            </p>';
                    }
                }
                else {
                    return 'Please use only defined elements, ' . esc_html($element) . ' isn\'t one.';
                }
            }
            else if(count($elements) >= 2) {
                return "There's a line with more than 2 elements which is not allowed.";
            }
        }
    }
    else {
        return "Please include fields in every line.";
    }
    
    if($style == 'divi') {
        $content .= '<div class="et_contact_bottom_container"><button type="submit" class="et_pb_contact_submit et_pb_button">' . $atts['label_submit'] . '</button></div></form></div>';
    }
    else {
        $content .= '<div><input type="submit" placeholder="' . $atts['label_submit'] . '" /></div></form>';
    }
    return $content;
}

function ajabiz_generate_user_password() {
    return "yes";
}

function diSubscribeToAjabizForm($hash, $email, $firstName = 'Friend', $lastName = null, $city = null, $telephone = null) {
    $data = array(
        'email' => $email,
        'firstName' => $firstName,
    );
    
    if(!empty($lastName)) {
      $data['lastName'] = $lastName;
    }
    
    if(!empty($city)) {
      $data['city'] = $city;
    }
    
    if(!empty($telephone)) {
      $data['telephone'] = $telephone;
    }
    
    $curl = curl_init('http://www.ajabiz.com/form/addContact/' . $hash);
    curl_setopt_array($curl, array(
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_POST => 1,
        CURLOPT_POSTFIELDS => $data
    ));
    $resp = curl_exec($curl);
    if (!curl_errno($curl)) {
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        switch ($httpCode) {
            case 200:
                return true;
                break;
            default:
                return false;
        }
    }
    return false;
}