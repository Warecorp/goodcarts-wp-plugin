<?php
/*
 * Plugin Name: GoodCarts Integration for WooCommerce
 * Plugin URI: https://goodcarts.co/woocommerce/
 * Description: Connect your woo-site with GoodCarts with this simple plugin.
 * Tested up to: 5.8
 * Version: 1.0.0
 * Author: Warecorp Inc.
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

if (!class_exists('Goodcarts_Integrations')) {

  require_once(__DIR__ . '/token.php');

  class Goodcarts_Integrations {

    /**
     * Tokenizer object
     */
    private $tokenizer;

    function __construct() {
      $this->debug = false;
      $this->gc_integrations_url = getenv('GC_INTEGRATION_URL', true) ? getenv('GC_INTEGRATION_URL', true) : 'https://apps.goodcarts.co';
      $this->gc_url = getenv('GC_URL', true) ? getenv('GC_URL', true) : 'https://my.goodcarts.co';
      $this->activated_key = 'GC_Activated';

      $this->tokenizer = new Goodcarts_Token();
      add_action('admin_init', [$this, 'check_requirements']);
      add_action('admin_menu', [$this, 'add_to_menu']);
      add_action('activate_plugin', [$this, 'add_activated_notice']);
      add_action('woocommerce_thankyou', [$this, 'tracking_banner'], 1 );
      add_action('woocommerce_thankyou', [$this, 'tracking'] );
      add_action('rest_api_init', [$this, 'register_api_routes']);
      add_action('rest_api_init', [$this, 'enable_CORS_headers'], 15 );
      add_action('deleted_plugin', [$this, 'clean_up_data']);
      add_action('deactivate_plugin', [$this, 'clean_up_data']);
    }

    function add_activated_notice() {
      add_option( $this->activated_key, '1' );
    }

    function write_log ( $log )  {
      if (!$this->debug) return;
      if ( is_array( $log ) || is_object( $log ) ) {
        error_log( print_r( $log, true ) );
      } else {
        error_log( $log );
      }
    }

    function check_requirements() {
      if ($this->is_https_used() && $this->is_woocommerce_activated()) {
        add_action( 'admin_notices', [ $this, 'installation_notice' ] );
      } else {
        deactivate_plugins( plugin_basename( __FILE__ ) ); 
        if ( isset( $_GET['activate'] ) ) {
          unset( $_GET['activate'] );
        }
      }
    }
    
    function is_https_used() {
      $api_url = get_rest_url(null);
      
      if ( stripos($api_url, 'https://' ) !== 0 ) {
        add_action( 'admin_notices', [ $this, 'need_https_notice' ] );
        return false;
      }
      return true;
    }
    
    function is_woocommerce_activated() {
      if ( is_admin() && current_user_can( 'activate_plugins' ) &&  !is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
        add_action( 'admin_notices', [ $this, 'need_wc_notice' ] );
        return false;
      }
      return true;
    }

    function need_wc_notice(){
      ?><div class="error"><p>Sorry, but Goodcarts requires the WooCommerce plugin to be installed and active.</p></div><?php
    }
    function need_https_notice(){
      ?><div class="error">
        <p>Sorry, but Goodcarts requires site to use only https. We don't support http - it's not secure.</p>
      </div><?php
    }
    
    function installation_notice() {
      $show_notification = get_option($this->activated_key);
      
      $is_plugins_page = (substr($_SERVER["PHP_SELF"], -11) == 'plugins.php');
      
      if ($is_plugins_page && $show_notification == '1' && function_exists("admin_url")) {
        echo '<div class="error"><p><strong>' .
        sprintf(__('Go through <a href="%s">GoodCarts installation Wizard</a> to enable plugin functionality.', 'goodcarts'),
        admin_url('admin.php?page=goodcarts')) .
        '</strong></p></div>';
        delete_option($this->activated_key);
      }
    }

    function add_to_menu() {
      $wc_icon = '';
      add_menu_page( 'GoodCarts', 'GoodCarts', 'edit_others_shop_orders', 'goodcarts', [$this, 'embed_ui'], $wc_icon );
    }
    
    function embed_ui() {
      //must check that the user has the required capability
      if (!current_user_can('edit_others_shop_orders')) {
        wp_die( __('You do not have sufficient permissions to access this page.') );
      }
      $token = $this->tokenizer->set_token_for_user(get_current_user_id());
      $api_url = get_rest_url(null);
      $gci_url = $this->gc_integrations_url . '/integrations/?platform=woocommerce&token=' . $token . '&api_url=' . $api_url;
      ?>
        <div class="wrap" style="display: flex; flex-direction: column; align-items: stretch; min-height: 100vh;">
          <script type="text/javascript">
            var gcIframeStr = '<iframe title="GoodCarts Settings" ' +
              'src="<?php echo esc_url($gci_url) ?>"' +
              'name="app-iframe" ' +
              'style="position: relative; border: none; width: 100%; flex: 1 1 0%; display: flex;"></iframe>'
            document.write(gcIframeStr);
          </script>  
        </div>
      <?php
    }
  

    // =============================================================================
    // Create GoodCart Banner
    // =============================================================================

    function tracking_banner() {
      $gc_hash = get_option("goodcarts_gc_hash");
      if (!$gc_hash) return;
      echo '<div id="gc-container" style="display: block; clear: both; margin-bottom: 2em;"></div>'
      ?>
      <script type="text/javascript">
          var _iGCBannerObj = function(params) {
            this.htmlElementId = 'gc-container';
            this.params = params;
            this.lS=function(s){ var head=document.head || document.getElementsByTagName("head" )[0] || document.documentElement; var script=document.createElement("script");script.async="async";script.src=s;head.insertBefore(script ,head.firstChild);},
            this.gc=function(){return document.getElementById(this.htmlElementId);};
            this.start=function() { var r=[];for(e in this.params){if(typeof(e)==='string'){r.push(e+'='+encodeURIComponent(this.params[e]));}}r.push('method=main');r.push('jsc=iGCCpnObj');
            this.lS('<?php echo $this->gc_url ?>/api/v1/wrapper?'+r.join('&'));}
          };
        var iGCCpnObj = null;
      </script>
    <?php 
    }

    // =============================================================================
    // Passing GoodCart Data
    // =============================================================================

    function tracking( $order_id ) {
      $gc_hash = get_option("goodcarts_gc_hash");
      if (!$gc_hash) return;
      $order = WC_Order_Factory::get_order( $order_id );
      
      $order_number = $order->get_id();
      $currency = $order->get_currency();
      $email = $order->get_billing_email();
      $total = $order->get_total();
      $coupon = implode(",", $order->get_coupon_codes());
    ?>

      <script type="text/javascript">
        var params = {
            'gc_hash': '<?php echo esc_js(get_option("goodcarts_gc_hash")); ?>',
            'customer_email': '<?php echo esc_attr($email); ?>',
            'order_id': '<?php echo esc_attr($order_number); ?>',
            'order_amount': '<?php echo esc_attr($total); ?>',
            'order_amount_type': '<?php echo esc_attr($currency); ?>',
            'promo_code': '<?php echo esc_attr($coupon); ?>'
        };
        iGCCpnObj = new _iGCBannerObj(params); iGCCpnObj.start();
      </script>
    <?php  
    }

    // =============================================================================
    // API end-points
    // =============================================================================

    // Returns GoodCarts User, WooCommerce auth token and Shop basic data at once
    // as it is required at once in UI

    function api_get_gc_user() {
      $gc_user = get_option('goodcarts_api_user');
      $wc_auth = get_option('goodcarts_wc_auth');
      return rest_ensure_response([ 
        'user' => $gc_user, 
        'wc_auth' => $wc_auth,
        'shop' => [
          'name' => get_bloginfo('name'),
          'domain' => get_bloginfo('url')
        ]
      ]);
    }

    // Updates GoodCarts user data
    function api_update_gc_user(WP_REST_Request $request) {
      if ($request['user']) {
        $user_data = [
          'token' => sanitize_text_field($request['user']['token']),
          'refresh_token' => sanitize_text_field($request['user']['refresh_token']),
          'full_name' => sanitize_text_field($request['user']['full_name']),
          'name' => sanitize_user($request['user']['name']),
          'email' => sanitize_email($request['user']['email'])
        ];
        update_option('goodcarts_api_user', $user_data);
      }
      $gc_user = get_option('goodcarts_api_user');
      return rest_ensure_response(['success' => true, 'user' => $gc_user]);
    }

    // Updates banner hash code gc_hash to be able to shop banner on final checkout success page
    function api_save_banner(WP_REST_Request $request) {
      if ($request['gc_hash']) {
        update_option('goodcarts_gc_hash', sanitize_text_field($request['gc_hash']));
        $gc_hash = get_option('goodcarts_gc_hash');
        return rest_ensure_response(['success' => true, 'gc_hash' => $gc_hash]);
      } else {
        return rest_ensure_response(['success' => false, 'request' => $request]);
      }
    }

    // Saves WooCommerce Auth data for SPA UI. 
    function api_update_wc_tokens(WP_REST_Request $request) {
      $wc_auth = [
        'consumer_key' => sanitize_text_field($request['consumer_key']),
        'consumer_secret' => sanitize_text_field($request['consumer_secret']),
      ];
      update_option('goodcarts_wc_auth', $wc_auth);
      
      return rest_ensure_response(['success' => true]);
    }

    function clean_up_data() {
      delete_option('gc_hash');
      delete_option('gctoken');
      delete_option('goodcarts_wc_auth');
      delete_option('goodcarts_api_user');
      delete_option('goodcarts_gc_hash');
    }

    /**
     * We use it to determine the current user from the access token in the Authorization header.
     * If no Authorization header exists, just returns null.
     */
    function get_current_user_id() {
      /**
       * Make sure to add the lines below to .htaccess
       * otherwise Apache may strip out the auth header.
       * RewriteCond %{HTTP:Authorization} ^(.*)
       * RewriteRule ^(.*) - [E=HTTP_AUTHORIZATION:%1]
       */
      // On some servers the headers are changed to upper or lowercase.
      $headers = array_change_key_case(function_exists('apache_request_headers')
        ? apache_request_headers() : $_SERVER, CASE_LOWER);
      $possibleAuthHeaderKeys = [
        'authorization', 
        'x-auth-token',
      ];
      $this->write_log("Headers are " . print_r($headers, true) );
      $authHeader = null;
      foreach ($possibleAuthHeaderKeys as $key) {
        if (!empty($headers[$key])) {
          $authHeader = $headers[$key];
          $this->write_log("authHeader is $authHeader");
          break;
        }
      }
      
      if (!empty($authHeader)) {
        // 7 = strlen('Bearer ');
        $access_token = substr($authHeader, 7);
        $this->write_log("Get user by access_token $access_token");
        $uid = $this->tokenizer->get_user_id_by_token($access_token);
        $this->write_log("Got user_id $uid");
        return $uid;
      }
      $this->write_log("Found no user from header");
      return null;
    }

    // Checks that user has permissions to work with API resource
    function api_permissions_check() {
      $this->write_log("======================");
      $this->write_log("api_permissions_check");
      $uid = $this->get_current_user_id();
      $this->write_log("User ID is $uid");
      if ($uid) {
        $this->write_log("get_user_by ID $uid");
        $user = get_user_by('id', intval($uid));
        if ( !empty($user) ) {
          $this->write_log("Found user for ID $uid");
        } else {
          $this->write_log("No user found for ID $uid");
        }
      }
      // Restrict endpoint to only users who have the capability.
      if ( $uid && !empty($user) ) {
        if ( !$user->has_cap( 'manage_options' ) ) {
          return new WP_Error( 'rest_forbidden', esc_html__( 'You are not authorized to work with this API.', 'goodcarts' ), array( 'status' => 401 ) );
        }
      } else {
        return new WP_Error( 'rest_forbidden', esc_html__( 'You are not authenticated.', 'goodcarts' ), array( 'status' => 401 ) );
      }
      return true;
    }


    /**
     * Registering the routes endpoints
     */
    function register_api_routes() {
      $base_path = 'goodcarts/v1';

      register_rest_route($base_path, '/gc_user', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => [$this, 'api_get_gc_user'],
        'permission_callback' => [$this, 'api_permissions_check'],
      ]);

      register_rest_route($base_path, '/gc_user', [
        'methods' => WP_REST_Server::EDITABLE,
        'callback' => [$this, 'api_update_gc_user'],
        'permission_callback' => [$this, 'api_permissions_check'],
      ]);

      // This route is only 
      register_rest_route($base_path, '/gc_wc_token', [
        'methods' => WP_REST_Server::EDITABLE,
        'callback' => [$this, 'api_update_wc_tokens'],
        'permission_callback' => '__return_true',
        'args' => [
          "consumer_key" => [
            'required' => true
          ],
          "consumer_secret" => [
            'required' => true
          ]
        ]
      ]);
  
      register_rest_route($base_path, '/gc_banner', [
        'methods' => WP_REST_Server::EDITABLE,
        'callback' => [$this, 'api_save_banner'],
        'permission_callback' => [$this, 'api_permissions_check'],
        'args' => [
          'gc_hash' => [
            'required' => true
          ]
        ]
      ]);
    }

    // As our SPA is loaded into iFrame from our integrations.goocarts.co domain 
    // we should allow CORS for API to let our APP work
    public function enable_CORS_headers() {
      add_filter( 'rest_pre_serve_request', function( $value ) {
        header( 'Access-Control-Allow-Headers: Authorization, X-WP-Nonce, Content-Type, X-Requested-With');
        header( 'Access-Control-Allow-Origin: *' );
        header( 'Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT' );
        header( 'Access-Control-Allow-Credentials: true' );
        return $value;
      } );
    }

  }
}
new Goodcarts_Integrations();

?>
