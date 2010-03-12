<?
/**
 * Locum is a software library that abstracts ILS functionality into a
 * catalog discovery layer for use with such things as bolt-on OPACs like
 * SOPAC.
 * @package Locum
 * @category Locum Connector
 * @author John Blyberg <john@blyberg.net>
 */

/**
 * This class serves as a PHP interface to the III patron API.
 * Functionally, it can stand alone as its own software library, but is categoried as a
 * connector tool for Locum.
 */
class iii_patronapi {

  public $iiiserver;
  public $bcode_length;

  /*
  * Class constructor
  */
  public function __construct() {
    $this->bcode_length = '14'; // You may need to override this value
  }

  /**
  * Returns patron data from the API in an easy-to-use array
  *
  * @param string $id Can be either a barcode number or a pnum, the function can tell which
  * @return boolean|array An array of patron record data for $id, FALSE if the patron record cannot be found
  */
  public function get_patronapi_data($id) {

    if (strlen($id) < $this->bcode_length) {  $id = ".p" . $id; }
    $apiurl = 'http://' . $this->iiiserver . ":4500/PATRONAPI/$id/dump";

    $api_contents = self::get_api_contents($apiurl);
    if (!$api_contents) return FALSE;

    $api_array_lines = explode("\n", $api_contents);
    while (strlen($api_data['PBARCODE']) != $this->bcode_length && !$api_data['ERRNUM']) {
      foreach ($api_array_lines as $api_line) {
        $api_line = str_replace("p=", "peq", $api_line);
        $api_line_arr = explode("=", $api_line);
        $regex_match = array("/\[(.*?)\]/","/\s/","/#/");
        $regex_replace = array('','','NUM');
        $key = trim(preg_replace($regex_match, $regex_replace, $api_line_arr[0]));
        $api_data[$key] = trim($api_line_arr[1]);
      }
    }
    return $api_data;
  }

  /**
  * Checks tha validity of an id/pin combo
  *
  * @param string $id Can be either a barcode number or a pnum, the function can tell which
  * @param string $pin The password/pin to use with $id
  * @return array An array of validation information
  */
  public function check_validation($id, $pin) {

    $pin = urlencode($pin);
    if (strlen($id) < $this->bcode_length) { $id = ".p" . $id; }
    $apiurl = 'http://' . $this->iiiserver . ":4500/PATRONAPI/$id/dump";

    $api_contents = self::get_api_contents($apiurl);

    $api_array_lines = explode("\n", $api_contents);
    foreach ($api_array_lines as $api_line) {
      $api_line_arr = explode("=", $api_line);
      $api_data[$api_line_arr[0]] = $api_line_arr[1];
    }

    return $api_data;
  }

  /**
  * An internal function to grab the API XML
  *
  * @param string $apiurl The formulated url to the patron API record
  * @return string Contents of the HTTP request from the Patron API
  */
  public function get_api_contents($apiurl) {
    $api_contents = file_get_contents($apiurl);
    $api_contents = trim(strip_tags($api_contents));
    return $api_contents;
  }

}
