<?php
/**
 * Locum is a software library that abstracts bibliographic social catalog data
 * and functionality.  It can then be used in a variety of applications to both
 * consume and contribute data from the repository.
 * @package Insurge
 * @author John Blyberg
 */


/**
 * This is the parent Insurge class for all insurge-related activity.
 * This is called as the parent by either the client or the server piece.
 */
class insurge {

  public $insurge_config;
  public $db;
  public $dsn;

  /**
   * Locum constructor.
   * This function prepares Locum for activity.
   */
  public function __construct() {
    if (function_exists('insurge_constructor_override')) {
      insurge_constructor_override($this);
      return;
    }
    
    ini_set('memory_limit','128M');
    $this->insurge_config = parse_ini_file('config/insurge.ini', true);

    // Take care of requirements
    require_once('MDB2.php');
    require($this->insurge_config['insurge_config']['dsn_file']);
    $this->dsn = $dsn;
  }

}