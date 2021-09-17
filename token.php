<?php
// Generates access token for our SPA. 
// Then this token is placed as part of load SPA Url.
// So our GoodCarts SPA can work with Wordpress and WooCommerce wia our plug-in API

class Goodcarts_Token {

  private static $TOKEN_BYTE_LENGTH = 32;
  private static $TOKEN_OPTION_PREFIX = 'gctkn_';

  /*
   * Returns WP user id from access token.
   * @param $access_token Access token of user
   * @return integer|null WP user is or null if nothing is found.
   */
  public function get_user_id_by_token($token) {
    $user_id = get_option(self::$TOKEN_OPTION_PREFIX.$token, null);
    if (!empty($user_id)) $user_id = intval($user_id);
    return $user_id;
  }

  // Generate token and use it as part of Key in Options table to save
  // associated user_id, so later we could get user by access token.
  public function set_token_for_user($user_id) {
    $token = $this->create_token($user_id);
    update_option(self::$TOKEN_OPTION_PREFIX.$token, strval($user_id));
    return $token;
  }

  /*
   * Returns token.
   */
  private function create_token($user_id) {
    return bin2hex(openssl_random_pseudo_bytes(self::$TOKEN_BYTE_LENGTH));
  }

}
