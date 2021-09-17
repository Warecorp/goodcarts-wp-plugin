<?php
// Generates access token for our SPA. 
// Then this token is placed as part of load SPA Url.
// So our GoodCarts SPA can work with Wordpress and WooCommerce wia our plug-in API

class Goodcarts_Token {

  private static $TOKEN_BYTE_LENGTH = 32;
  private static $TOKEN_OPTION_NAME = 'gctoken';
  private static $DEBUG = false;
  /*
   * Returns WP user id from access token.
   * @param $access_token Access token of user
   * @return integer|null WP user is or null if nothing is found.
   */
  public function get_user_id_by_token($token) {
    self::$DEBUG && error_log("==============================get_user_id_by_token $token");
    $tokens = get_option(self::$TOKEN_OPTION_NAME, []);
    self::$DEBUG && error_log(var_export($tokens, true));
    $uid_from_token = $tokens[$token] || false;
    self::$DEBUG && error_log("==============================user_id is $uid_from_token");
    return $uid_from_token;
  }

  // Generate token and use it as part of Key in Options table to save
  // associated user_id, so later we could get user by access token.
  public function set_token_for_user($uid) {
    self::$DEBUG && error_log("==============================set_token_for_user $uid");
    $tokens = get_option(self::$TOKEN_OPTION_NAME, []);
    $token = $tokens[$uid];
    if ($token) {
      unset($tokens[$uid]);
      unset($tokens[$token]);
    }
    $token = $this->create_token($uid);
    $tokens[$uid] = $token;
    $tokens[$token] = $uid;
    self::$DEBUG && error_log("==============================update_option");
    self::$DEBUG && error_log(self::$TOKEN_OPTION_NAME);
    self::$DEBUG && error_log(var_export($tokens, true));
    update_option(self::$TOKEN_OPTION_NAME, $tokens);
    return $token;
  }

  /*
   * Returns token.
   */
  private function create_token($uid) {
    return bin2hex(openssl_random_pseudo_bytes(self::$TOKEN_BYTE_LENGTH));
  }

}
