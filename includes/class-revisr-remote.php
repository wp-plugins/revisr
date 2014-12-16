<?php
/**
 * class-revisr-remote.php
 *
 * Processes remote updates for Revisr.
 *
 * @package   Revisr
 * @license   GPLv3
 * @link      https://revisr.io
 * @copyright 2014 Expanded Fronts, LLC
 */

// Disallow direct access.
if ( ! defined( 'ABSPATH' ) ) exit;

class Revisr_Remote extends Revisr_Admin {


	/**
	 * Returns the current token, creating one if it does not exist.
	 * @access public
	 * @return string|boolean The token, or false on complete failure.
	 */
	public function get_token() {
		$check = $this->git->run( 'config revisr.token' );

		if ( $check === false ) {
			$token = wp_generate_password( 16, false, false );
			$save  = $this->git->run( "config revisr.token $token" );

			if ( $save !== false ) {
				return $token;
			} else {
				return false;
			}
		} elseif ( is_array( $check ) ) {
			return $check[0];
		} else {
			return false;
		}
	}

	/**
	 * Verifies a token is valid.
	 * @access public
	 * @return boolean
	 */
	public function check_token() {
		if ( isset( $_REQUEST['token'] ) ) {
			$safe_token = $this->git->run( 'config revisr.token' );
			if ( is_array( $safe_token ) ) {
				if ( $safe_token[0] === $_REQUEST['token'] ) {
					return true;
				}
			}			
		}
		wp_die( __( 'Cheatin&#8217; uh?', 'revisr' ) );
	}

	/**
	 * Sends a new HTTP request to the live site.
	 * @access public
	 */
	public function send_request() {
		$body 	= array(
			'action' 		=> 'revisr_update'
		);
		$args 	= array(
			'method' 		=> 'POST',
			'timeout'		=> '15',
			'redirection'	=> '5',
			'httpversion'	=> '1.0',
			'blocking'		=> true,
			'headers'		=> array(),
			'body'			=> $body
		);

		// Get the URL and send the request.
		$get_url = $this->git->config_revisr_url( 'webhook' );

		if ( $get_url !== false ) {
			$webhook = urldecode( $get_url );
			$request = wp_remote_post( $webhook, $args );
			if ( is_wp_error( $request ) ) {
				Revisr_Admin::log( __( 'Error contacting webhook URL.', 'revisr' ), 'error' );
			} else {
				Revisr_Admin::log( __( 'Sent update request to the webhook.', 'revisr' ), 'push' );
			}
		}
	}
}