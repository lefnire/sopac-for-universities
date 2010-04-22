<?php
/**
 * Locum is a software library that abstracts ILS functionality into a
 * catalog discovery layer for use with such things as bolt-on OPACs like
 * SOPAC.
 *
 * This file allows developers to hook in to the Locum framework and replace existing class
 * functions without altering the core project files.  This file is not required for operation
 * and can safely be removed if you wish.  For more inormation, read the Locum Developer's 
 * Guide on http://thesocialopac.net
 *
 * @package Locum
 * @author John Blyberg
 */
 
 /**
  * Override class for locum
  */
class locum_hook extends locum {
  
}

 /**
  * Override class for locum_server
  */
class locum_server_hook extends locum_server {
  
}

 /**
  * Override class for locum_client
  */
class locum_client_hook extends locum_client {

}