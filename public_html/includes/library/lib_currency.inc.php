<?php
  
  class currency {
    public static $currencies;
    public static $selected;
    
    public static function construct() {
    }
    
    public static function load_dependencies() {
      
    // Bind selected to session
      if (!isset(session::$data['currency']) || !is_array(session::$data['currency'])) session::$data['currency'] = array();
      self::$selected = &session::$data['currency'];
      
      self::load();
      
    // Set currency, if not set
      if (empty(self::$selected) || empty(self::$currencies[self::$selected['code']]['status'])) self::set();
    }
    
    public static function startup() {
    
    // Reload currencies if not UTF-8
      if (strtoupper(language::$selected['charset']) != 'UTF-8') {
        self::load();
        self::set(self::$selected['code']);
      }
      
      if (!empty($_POST['set_currency'])) {
        self::set($_POST['set_currency']);
        header('Location: '. $_SERVER['REQUEST_URI']);
        exit;
      }
    }
    
    //public static function before_capture() {
    //}
    
    //public static function after_capture() {
    //}
    
    //public static function prepare_output() {
    //}
    
    //public static function before_output() {
    //}
    
    //public static function shutdown() {
    //}
    
    ######################################################################
    
    public static function load() {    
      
      self::$currencies = array();
      
    // Get currencies from database
      $currencies_query = database::query(
        "select * from ". DB_TABLE_CURRENCIES ."
        where status
        order by priority;"
      );
      while ($row = database::fetch($currencies_query)) {
        self::$currencies[$row['code']] = $row;
      }
    }
    
    public static function set($code=null) {
      
      if (empty($code)) $code = self::identify();
      
      if (!isset(self::$currencies[$code])) {
        trigger_error('Cannot set unsupported currency ('. $code .')', E_USER_WARNING);
        $code = self::identify();
      }
      
      session::$data['currency'] = self::$currencies[$code];
      setcookie('currency_code', $code, time()+(60*60*24*30), WS_DIR_HTTP_HOME);
    }
    
    public static function identify() {
      
    // Set currency from cookie
      if (!empty($_COOKIE['currency_code']) && isset(self::$currencies[$_COOKIE['currency_code']])) {
        return $_COOKIE['currency_code'];
      }
      
    // Get country from browser
      if (!empty($_SERVER['HTTP_ACCEPT_LANGUAGE']) && preg_match('#^([a-z]{2}-[A-Z]{2})#', $_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        if (preg_match('/-([A-Z]{2})/', $_SERVER['HTTP_ACCEPT_LANGUAGE'], $matches)) {
          if (!empty($matches[1])) $country_code = $matches[1];
        }
        if (!empty($country_code)) {
          $countries_query = database::query(
            "select * from ". DB_TABLE_COUNTRIES ."
            where iso_code_2 = '". database::input($country_code) ."'
            limit 1;"
          );
          $country = database::fetch($countries_query);
          
          if (!empty($country['currency_code']) && isset(self::$currencies[$country['currency_code']])) {
            return $country['currency_code'];
          }
        }
      }
      
    // Return default currency
      if (isset(self::$currencies[settings::get('default_currency_code')])) return settings::get('default_currency_code');
      
    // Return first currency
      $currencies = array_keys(self::$currencies);
      return array_shift($currencies);
    }
    
    public static function calculate($value, $to, $from=null) {
      
      if (empty($from)) $from = settings::get('store_currency_code');
      
      if (!isset(self::$currencies[$from])) trigger_error('Currency ('. $from .') does not exist', E_USER_WARNING);
      if (!isset(self::$currencies[$to])) trigger_error('Currency ('. $to .') does not exist', E_USER_WARNING);
      
      return $value / self::$currencies[$from]['value'] * self::$currencies[$to]['value'];
    }
    
    public static function convert($value, $from=null, $to) {
      return self::calculate($value, $to, $from);
    }
    
    public static function format($value, $auto_decimals=true, $raw=false, $currency_code=null, $currency_value=null) {
      
      if ($raw) return self::format_raw($value, $currency_code, $currency_value);
      
      if (empty($currency_code)) $currency_code = self::$selected['code'];
      if (!isset(self::$currencies[$currency_code])) trigger_error('Currency ('. $currency_code .') does not exist', E_USER_WARNING);

      if ($currency_value === null) $currency_value = currency::$currencies[$currency_code]['value'];
      
      $value = round($value * $currency_value, currency::$currencies[$currency_code]['decimals']);
      
      if ($auto_decimals == false || $value - floor($value) > 0) {
        $decimals = (int)self::$currencies[$currency_code]['decimals'];
      } else {
        $decimals = 0;
      }
      
      return self::$currencies[$currency_code]['prefix'] . number_format($value, $decimals, language::$selected['decimal_point'], language::$selected['thousands_sep']) . self::$currencies[$currency_code]['suffix'];
    }
    
    public static function format_raw($value, $currency_code=null, $currency_value=null) {
      
      if (empty($currency_code)) $currency_code = self::$selected['code'];
      if (!isset(self::$currencies[$currency_code])) trigger_error('Currency ('. $currency_code .') does not exist', E_USER_WARNING);
      
      if ($currency_value === null) $currency_value = currency::$currencies[$currency_code]['value'];
      
      return number_format($value * $currency_value, currency::$currencies[$currency_code]['decimals'], '.', '');
    }
    
  // Round a store currency amount in a remote currency
    public static function round($value, $currency_code) {
      
      if (empty($currency_code)) $currency_code = self::$selected['code'];
      if (!isset(self::$currencies[$currency_code])) trigger_error('Currency ('. $currency_code .') does not exist', E_USER_WARNING);
      
      $value = self::convert($value, settings::get('store_currency_code'), $currency_code);
      $value = round($value, self::$currencies[$currency_code]['decimals']);
      $value = self::convert($value, settings::get('store_currency_code'), $currency_code);
      
      return $value;
    }
  }
  
?>