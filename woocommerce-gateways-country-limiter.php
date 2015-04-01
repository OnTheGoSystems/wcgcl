<?php
/**
 * Plugin Name: WooCommerce Gateways Country Limiter
 * Plugin URI: http://www.onthegosystems.com
 * Description: Allows showing checkout payment options according to the client's billing country.
 * Version: 1.1
 * Author: OnTheGoSystems
 * Author URI: http://www.onthegosystems.com
 * Requires at least: 3.8
 * Tested up to: 4.2
 * 
 * Text Domain: woocommerce-gateways-country-limiter
 * Domain Path: /i18n/languages/
 * 
 *
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

if ( ! class_exists( 'WooCommerce_Gateways_Country_Limiter' ) ) :

final class WooCommerce_Gateways_Country_Limiter{
    
    public $settings;
    public $sections = array();
    protected static $_instance = null;
    
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }
    
    public function __construct() {
        
        
        $this->settings = get_option('woocommerce_gateways_country_limiter');
        
        add_action('init', array($this, 'init'), 10);
         
    }
    
    public static function woocommerce_inactive_notice() {
        if ( current_user_can( 'activate_plugins' ) ) : ?>
        <div id="message" class="error">
            <p><?php printf( __( '%sWooCommerce Gateways Country Limiter is inactive.%s %sWooCommerce%s must be active for it to work. Please %sinstall & activate WooCommerce%s', 'wcpgpl' ), '<strong>', '</strong>', '<a href="http://wordpress.org/extend/plugins/woocommerce/">', '</a>', '<a href="' . esc_url(admin_url( 'plugins.php' )) . '">', '&nbsp;&raquo;</a>' ); ?></p>
        </div>
        <?php    endif;
    }
    
    function init(){

        if ( ! class_exists( 'WooCommerce' ) && ! class_exists( 'Woocommerce' ) ){
            add_action( 'admin_notices', array( 'WooCommerce_Gateways_Country_Limiter' ,'woocommerce_inactive_notice' ));
            return;
        }
        
        $woocommerce = function_exists('WC') ? WC() : $GLOBALS['woocommerce']; //// Before WC 2.1.x 
        
        $payment_gateways = $woocommerce->payment_gateways->payment_gateways();
        
        $current_section = empty( $_GET['section'] ) ? '' : sanitize_title( $_GET['section'] );
        
        foreach ( $payment_gateways as $id => $gateway ) {
            $title = empty( $gateway->method_title ) ? ucfirst( $gateway->id ) : $gateway->method_title;
            $this->sections[ strtolower( get_class( $gateway ) ) ] = array('title' => esc_html( $title ), 'id'    => $id);
        }
        
        if(is_admin() && !empty($current_section)){        
            add_action( 'woocommerce_settings_checkout', array( $this, 'country_options_output' ), 1000 );            
            add_action( 'woocommerce_update_options_checkout', array( $this, 'country_options_update' ), 1000 ); 
                        
        }
        
        add_filter( 'woocommerce_available_payment_gateways', array( $this, 'filter_by_country' ), 1000 );             
        
        //defaults
        foreach ( $payment_gateways as $gateway_id => $gateway ) {
            if(!isset($this->settings[$gateway_id]['option'])){
                $this->settings[$gateway_id]['option'] = 'all';
            }
            if(!isset($this->settings[$gateway_id]['countries']) || !is_array($this->settings[$gateway_id]['countries'])){
                $this->settings[$gateway_id]['countries'] = array();
            }
        }
            
        if(is_admin() && !empty($current_section)){        
            if(isset($this->sections[$current_section])){    
                $gateway_id = $this->sections[$current_section]['id'];
                wc_enqueue_js("
                    
                    jQuery(document).ready(function(){
                        var current_option = jQuery('input[name={$gateway_id}_option]:checked');
                        if(current_option.val() == 'all_except' || current_option.val() == 'selected'){
                            jQuery('#pgcl_countries_list').show();
                            current_option.parent().parent().append(jQuery('#pgcl_countries_list'));                    
                        }
                        
                        jQuery('input[name={$gateway_id}_option]').change(function(){
                        
                            if(jQuery(this).val() == 'all_except' || jQuery(this).val() == 'selected'){
                                jQuery(this).parent().parent().append(jQuery('#pgcl_countries_list'));                   
                                jQuery('#pgcl_countries_list').show();
                            }else{
                                jQuery('#pgcl_countries_list').hide();
                            }
                        
                        })
                    })
                    
                ");
            }
        }
    }
    
    public function load_plugin_textdomain() {
        $locale = get_locale();

        load_textdomain( 'woocommerce-gateways-country-limiter', dirname( __FILE__ ) . "/i18n/languages/woocommerce-gateways-country-limiter-$locale.mo" );
        
    }
    
    function country_options_output(){
        global $current_section;
        
        if(!empty($current_section) && !empty($this->sections[$current_section])):
        
            $gateway_id = $this->sections[$current_section]['id'];
        
        ?>
        <table class="form-table">
            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label for="<?php echo $gateway_id ?>_country_options"><?php _e('Country availability', 'wcpgpl') ?></label>
                    <img class="help_tip" data-tip="<?php esc_attr_e( 'Configure per country availability for this payment gateway', 'wcpgpl' ) ?>" src="<?php echo esc_url( WC()->plugin_url() ) ?>/assets/images/help.png" height="16" width="16" />
                </th>                
                
                <td class="forminp forminp-radio">
                    <fieldset>
                        <ul>
                            <li>
                                <input type="hidden" name="woocommerce_gateways_country_limiter" value="<?php echo $gateway_id  ?>" />
                                <label>
                                    <input class="input-text regular-input " type="radio" name="<?php echo $gateway_id ?>_option" value="all" checked="checked" style="width: auto;" <?php checked('all', $this->settings[$gateway_id]['option']); ?> /><?php _e( 'Available for all countries', 'wcpgpl' ) ?>
                                </label>
                                
                            </li>                        
                            <li>                                
                                <label>
                                    <input class="input-text regular-input " type="radio" name="<?php echo $gateway_id ?>_option" value="all_except" style="width: auto;" <?php checked('all_except', $this->settings[$gateway_id]['option']); ?> /><?php _e( 'All countries except selected', 'wcpgpl' ) ?>
                                </label>
                                
                            </li>
                            <li>
                                <label>
                                    <input class="input-text regular-input " type="radio" name="<?php echo $gateway_id ?>_option" value="selected" style="width: auto;" <?php checked('selected', $this->settings[$gateway_id]['option']); ?> /><?php _e( 'Only selected countries', 'wcpgpl' ) ?>
                                </label>
                            </li>   
                        </ul>
                        
                        <div id="pgcl_countries_list" style="display:none;">
                            <select name="<?php echo $gateway_id ?>_countries[]" multiple="multiple" title="<?php esc_attr_e('Country', 'wcpgpl') ?>" class="chosen_select" data-placeholder="<?php esc_attr_e( 'Select countries&hellip;', 'wcpgpl' ); ?>">
                            <?php foreach(WC()->countries->countries as $code => $country): ?>
                            <option value="<?php echo esc_attr($code) ?>" <?php if(in_array($code, (array)$this->settings[$gateway_id]['countries'])): ?>selected="selected"<?php endif; ?>><?php echo esc_html($country); ?></option>
                            <?php endforeach; ?>                                
                            </select>
                        </div>                     
                        
                    </fieldset>
                </td>
                
            </tr>
        </table>
        
        <?php
        
        endif;
        
    }
    
    function country_options_update(){
        
        
        
        if( isset( $_POST['woocommerce_gateways_country_limiter'] ) && $_POST['woocommerce_gateways_country_limiter'] ){
            
            $gateway = $_POST['woocommerce_gateways_country_limiter'];
            
            if( isset( $_POST[$gateway . '_option'] ) && in_array( $_POST[$gateway . '_option'], array( 'all', 'all_except', 'selected' ) ) ){
                $this->settings[$gateway]['option']    = $_POST[$gateway . '_option'];    
            }
            
            $this->settings[$gateway]['countries'] = isset( $_POST[$gateway . '_countries'] ) ?  array_map('esc_attr', (array)$_POST[$gateway . '_countries']) : array();
            update_option( 'woocommerce_gateways_country_limiter', $this->settings );
            
        }
        
    }
    
    function filter_by_country($payment_gateways){
        
        $customer_country = WC()->customer->get_country();
        foreach($payment_gateways as $gateway_id => $gateway){
            if(
                $this->settings[$gateway_id]['option'] == 'all_except' && in_array($customer_country, $this->settings[$gateway_id]['countries']) || 
                $this->settings[$gateway_id]['option'] == 'selected' && !in_array($customer_country, $this->settings[$gateway_id]['countries'])
            ){
                unset($payment_gateways[$gateway_id]);    
            } 
            
        }
        
        return $payment_gateways;
    }
}


function WooCommerce_Gateways_Country_Limiter(){
    return WooCommerce_Gateways_Country_Limiter::instance();
}

WooCommerce_Gateways_Country_Limiter();


// WooCommerce 2.0.x backward compatibility
// wc_enqueue_js
add_action('admin_head', 'wc_gcl_wc_backwards_compatibility', 100);



function wc_gcl_wc_backwards_compatibility(){
    if(!function_exists('wc_enqueue_js')){
        function wc_enqueue_js($code){
            global $woocommerce;
            return $woocommerce->add_inline_js($code) ;
        }
        
    }
}


endif;
