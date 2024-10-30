<?php
defined( 'ABSPATH' ) OR exit;
/*
Plugin Name: WooCommerce MineWhat
Description: WooCommerce MineWhat  plugin
Author: MineWhat Inc
Author URI: http://www.minewhat.com
Version: 1.0.12

	Copyright: © 2013 MineWhat Inc (email : support@minewhat.com )
	License: GNU General Public License v3.0
    License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

/**
 * Check if WooCommerce is active
 */
if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {

    if (!class_exists('WC_MineWhat')) {


        class WC_MineWhat
        {
            public function __construct()
            {

                $this->id = 'minewhat_analytics';
                $this->method_inject = __('MineWhat Product Analytics', 'woocommerce');
                $this->method_description = __('MineWhat Product Analytics provides insights about
                the products in your website.', 'woocommerce');

                // Define user set variables
                $this->mw_event_tracking_enabled = "yes";

                // Set class property
                $this->options = get_option('mw_option_name');

                //add_filter('query_vars', array($this, 'mw_add_query_vars_filter'));

                // called only after woocommerce has finished loading

                add_action('woocommerce_thankyou', array($this, 'minewhat_tracking_buy'));

                add_action('woocommerce_add_to_cart', array($this, 'minewhat_tracking_cart_set'),10,6);

                add_action('wp_head', array($this, 'minewhat_tracking_view'));

                add_action('wp_head', array($this, 'minewhat_collection_script'));

                if (is_admin()) {
                    add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'action_links'));
                    add_action('admin_menu', array($this, 'add_plugin_page'));
                    add_action('admin_init', array($this, 'page_init'));
                }

            }

            /**
             * action_links function.
             *
             * @access public
             * @param mixed $links
             * @return void
             */
            public function action_links($links)
            {

                $plugin_links = array(
                    '<a href="' . admin_url('options-general.php?page=mw-setting-admin') . '">'
                    . __('Settings', 'woocommerce') . '</a>',
                );

                return array_merge($plugin_links, $links);
            }


            /**
             * Add options page
             */
            public function add_plugin_page()
            {
                // This page will be under "Settings"
                add_options_page(
                    'Settings Admin',
                    'MineWhat',
                    'manage_options',
                    'mw-setting-admin',
                    array($this, 'create_admin_page')
                );
            }

            public function mw_get_version() {
                $plugin_data = get_plugin_data( __FILE__ );
                $plugin_version = $plugin_data['Version'];
                return $plugin_version;
            }

            /**
             * Options page callback
             */
            public function create_admin_page()
            {

                ?>
                <div class="wrap">
                    <?php screen_icon(); ?>
                    <h2>MineWhat Settings</h2>

                    <?php
                     $info = "ver=". $this->mw_get_version()
                     . "&url=" . get_permalink(woocommerce_get_page_id('shop'));

                    ?>

                    <script type="text/javascript"
                    src="https://app.minewhat.com/stats/wooinstall?<?php echo $info;?>">
                    </script>

                    <form method="post" action="options.php">
                        <?php
                        // This prints out all hidden setting fields
                        settings_fields('mw_option_group');
                        do_settings_sections('mw-setting-admin');
                        submit_button();
                        ?>
                    </form>
                </div>
            <?php
            }

            /**
             * Register and add settings
             */
            public function page_init()
            {
                register_setting(
                    'mw_option_group', // Option group
                    'mw_option_name', // Option name
                    array($this, 'sanitize') // Sanitize
                );

                add_settings_section(
                    'setting_section_id', // ID
                    'One Time Settings', //
                    array($this, 'print_section_info'), // Callback
                    'mw-setting-admin' // Page
                );

                add_settings_field(
                    'script_data', // ID
                    'Script', //
                    array($this, 'script_data_callback'), // Callback
                    'mw-setting-admin', // Page
                    'setting_section_id' // Section
                );

                add_settings_field(
                    'inject',
                    'Enable Script',
                    array($this, 'inject_callback'),
                    'mw-setting-admin',
                    'setting_section_id'
                );
            }

            /**
             * Sanitize each setting field as needed
             *
             * @param array $input Contains all settings fields as array keys
             */
            public function sanitize($input)
            {
                return $input;
            }

            /**
             * Print the Section text
             */
            public function print_section_info()
            {
                print "Please copy-and-paste the Minewhat script into the script box
                (If you haven't got it, signup <a href='https://minewhat.com?p=woosettings'>here</a>)";
            }

            /**
             * Get the settings option array and print one of its values
             */
            public function script_data_callback()
            {
                printf(
                    '<textarea rows="15" cols="50" id="script_data" name="mw_option_name[script_data]" > %s </textarea>',
                    esc_attr($this->options['script_data'])
                );
            }

            /**
             * Get the settings option array and print one of its values
             */
            public function inject_callback()
            {
                printf(

                    '<input type="checkbox" id="inject" name="mw_option_name[inject]" value="1"  %s />',
                    checked(1, $this->options['inject'], false)

                );
            }

            //function mw_add_query_vars_filter($vars)
            //{
            //    $vars[] = "added-to-cart";
            //    return $vars;
            //}

            /**
             * Analytics standard tracking
             *
             * @access public
             * @return void
             */
            function minewhat_tracking_view()
            {
                global $woocommerce;
                global $mwcart;
                if ($this->is_tracking_disabled()) return;


                $code = "
					                var _mwapi = _mwapi || [];
				                ";

                if (is_product()) {


                    if(isset($mwcart)){
                    $code .= $this->minewhat_tracking_cart();
                    }else{

                    $_product = get_product();

                    $code .= "_mwapi.push(['trackEvent','product',";
                    $code .= "{pid:'" . esc_js( $_product->id) . "',";
                    /**
                     * for variant guys put the children.
                    */
                    if($_product->is_type("variable")){
                        $code .= "associated_ids:" . esc_js(json_encode($_product->get_children())) . "}";
                    }else{
                        $code .= "associated_ids:[]}";
                    }

                    $code .= "]);";

                   /**
                    * Fill the data part with
                    */
                    $product_cat_id  = array();
                    $terms = get_the_terms( $_product->id, 'product_cat' );
                    foreach ($terms as $term) {
                        $product_cat_id[] = $term->slug;

                    }

                    $mwdata = array();
                    $mwdata['product'] = array();
                    $mwdata['product']['id'] = $_product->id;
                    $mwdata['product']['sku'] = $_product->get_sku();
                    $mwdata['product']['cat'] = array();
                    foreach($product_cat_id as $category) {
                      $mwdata['product']['cat'][] = $category;
                    }
                    $mwdata['product']['price'] = $_product->price;

                    echo '<script type="text/mwdata">//<![CDATA[' . json_encode($mwdata) . '//]]></script>';

                  }
                }

                echo '<script type="text/javascript">' . $code . '</script>';


            }


            /**
             * Analytics standard tracking
             *
             * @access public
             * @return void
             */
            function minewhat_tracking_cart_set()
            {
                global $woocommerce;
                global $mwcart;
                $args = func_get_args();

                if(!isset($mwcart))
                    $mwcart = array();

                $cart_item = array();
                if($args[3])
                  $cart_item["product"] = new WC_Product_Variation($args[3]);
                else
                  $cart_item["product"] = new WC_Product($args[1]);

                $cart_item["qty"] = $args[2];
                $mwcart[] = $cart_item;

            }
            /**
             * Analytics standard tracking
             *
             * @access public
             * @return void
             */
            function minewhat_tracking_cart()
            {
                global $woocommerce;
                global $mwcart;

                $code = '';
                foreach ( $mwcart as $_item) {
                  $_product = $_item["product"];
                  $code .= "_mwapi.push(['trackEvent','addtocart',";
                  if($_product->is_type("variation")){
                      $code .= "{pid:'" . esc_js($_product->variation_id) . "',";
                      $code .= "sku:'" . esc_js($_product->get_sku()) . "',";
                      $code .= "parent_id:'" . esc_js($_product->parent->id) . "',";
                      $code .= "qty:" . esc_js($_item["qty"]) . ",";
                      $code .= "bundle:" . esc_js(json_encode(array())) . "}";
                   }
                   else{
                      $code .= "{pid:'" . esc_js( $_product->id) . "',";
                      $code .= "sku:'" . esc_js($_product->get_sku()) . "',";
                      $code .= "parent_pid:'',";
                      $code .= "qty:" . esc_js($_item["qty"]) . ",";
                      $code .= "bundle:" . esc_js(json_encode(array())) . "}";
                  }

                  $code .= "]);";
                }

                unset($mwcart);
                return $code;

            }


            /**
             * Analytics standard script
             *
             * @access public
             * @return void
             */
            function minewhat_collection_script()
            {
                global $woocommerce;

                if ($this->is_tracking_disabled() && !$this->options['inject']) return;

                $code = $this->options['script_data'];

                echo $code;

                if ( is_user_logged_in()) {
                    global $current_user;
                    $userTrackingCode = "<script type='text/javascript'>var _mwapi = _mwapi || [];_mwapi.push(['trackUser','".$current_user->user_email."']);</script>";
                    echo $userTrackingCode;
                }
            }

            /**
             * eCommerce tracking
             *
             * @access public
             * @param mixed $order_id
             * @return void
             */
            function minewhat_tracking_buy($order_id)
            {
                global $woocommerce;

                if ($this->is_tracking_disabled() || get_post_meta($order_id, '_mw_tracked', true) == 1)
                    return;

                // Get the order and output tracking code
                $order = new WC_Order($order_id);

                // Doing eCommerce tracking so unhook standard tracking from the footer
                remove_action('wp_head', array($this, 'minewhat_tracking_view'));

                $code = "
					                var _mwapi = _mwapi || [];
				                ";

                // Order items
                if ($order->get_items()) {

                    $code .= "_mwapi.push(['trackEvent','buy',{platform:'woocommerce',products:";

                    $items = array();

                    foreach ($order->get_items() as $item) {

                        $_product = $order->get_product_from_item($item);

                        $_item = array();

                        if($_product->is_type("variation")){
                            $_item['pid'] = esc_js( $_product->variation_id);
                            $_item['qty'] = esc_js($item['qty']);
                            $_item['price'] = esc_js($_product->price);
                            $_item['sku'] = esc_js($_product->get_sku());
                            $_item['parent_pid'] = esc_js($_product->parent->id);
                       }
                        else{
                          $_item['pid'] = esc_js($_product->id);
                          $_item['qty'] = esc_js($item['qty']);
                          $_item['price'] = esc_js($_product->price);
                          $_item['sku'] = esc_js($_product->get_sku());
                          $_item['parent_pid'] = '';
                        }
                        $items[] = $_item;

                    }

                    $code .= json_encode($items) .",";
                    $code .= "order:{order_number:'".$order_id."',payment:'".$order->payment_method_title."',email:'".$order->billing_email."'}}]);";
                    /* Add coupon code TODO */
                }


                echo '<script type="text/javascript">' . $code . '</script>';

                update_post_meta($order_id, '_mw_tracked', 1);
            }

            /**
             * Check if tracking is disabled
             *
             * @access private
             * @return bool
             */
            private function is_tracking_disabled()
            {

                if (is_admin() || current_user_can('manage_options')
                    || ($this->mw_event_tracking_enabled == "no")) return true;

            }

            /**
             * Take care of anything that needs woocommerce to be loaded.
             * For instance, if we need access to the $woocommerce global
             */
            public function woocommerce_loaded()
            {


            }

            public function install() {
                add_option('mw_plugin_do_activation_redirect', true);
            }

            public function redirect_settings_ifneeded() {
                if (get_option('mw_plugin_do_activation_redirect', false)) {
                    delete_option('mw_plugin_do_activation_redirect');
                    if(!isset($_GET['activate-multi']))
                    {
                        wp_redirect("options-general.php?page=mw-setting-admin");
                    }
                }
            }


        }

        // finally instantiate our plugin class and add it to the set of globals
        $GLOBALS['wc_mineWhat'] = new WC_MineWhat();
    }

    register_activation_hook( __FILE__, array( 'WC_MineWhat', 'install' ) );
    add_action('admin_init', array( 'WC_MineWhat', 'redirect_settings_ifneeded' ));
}
