<?php
/**
 * Theme-led registration and email confirmation forms.
 *
 * @package AssemblyPress
 */

namespace AssemblyPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Joining surfaces. */
final class Joining {
	/** Form feedback.
	 *
	 * @var string
	 */
	private static $notice = '';
	/** Whether feedback is an error.
	 *
	 * @var bool
	 */
	private static $error = false;
	/** Submitted text, retained only in the failed request.
	 *
	 * @var array
	 */
	private static $values = array();

	/** Register joining block.
	 *
	 * @return void
	 */
	public static function register() {
		register_block_type( ASSEMBLYPRESS_PLUGIN_DIR . 'blocks/registration', array( 'render_callback' => array( self::class, 'render' ) ) );
	}

	/** Link core registration to the configured joining page.
	 *
	 * @param string $url Existing URL.
	 * @return string
	 */
	public static function registration_url( $url ) {
		$page = Settings::get()['join_page'];
		return $page && 'publish' === get_post_status( $page ) ? get_permalink( $page ) : $url;
	}

	/** Prevent core public registration bypassing configured membership policy.
	 *
	 * @param \WP_Error $errors Core registration errors.
	 * @return \WP_Error
	 */
	public static function core_registration( $errors ) {
		if ( get_option( 'assemblypress_settings' ) ) {
			$errors->add( 'assemblypress_joining', __( 'Use this site’s membership registration page or contact the site owner to join.', 'assemblypress' ) );
		}
		return $errors;
	}

	/** Process nonce-protected submissions; GET links never consume tokens.
	 *
	 * @return void
	 */
	public static function handle() {
		self::$notice = '';
		self::$error  = false;
		self::$values = array();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only selects a read-only confirmation surface.
		if ( isset( $_GET['ap_membership'] ) ) {
			nocache_headers();
			header( 'Referrer-Policy: no-referrer' );
			add_filter(
				'wp_robots',
				static function ( $robots ) {
					$robots['noindex'] = true;
					return $robots;
				}
			);
			add_filter(
				'template_include',
				static function () {
					return ASSEMBLYPRESS_PLUGIN_DIR . 'includes/templates/membership.php';
				}
			);
		}
		if ( ! isset( $_POST['assemblypress_join_nonce'] ) ) {
			return;
		}
		nocache_headers();
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['assemblypress_join_nonce'] ) ), 'assemblypress_join' ) ) {
			self::$notice = __( 'Your form expired. Refresh the page and try again.', 'assemblypress' );
			self::$error  = true;
			return;
		}
		// A short per-address limit reduces email abuse without trusting forwarded headers.
		$address = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$key     = 'ap_join_' . hash_hmac( 'sha256', $address, wp_salt() );
		$count   = (int) get_transient( $key );
		if ( $count >= 10 || ! empty( $_POST['ap_website'] ) ) {
			self::$notice = __( 'Please wait before trying again.', 'assemblypress' );
			self::$error  = true;
			return;
		}
		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
		$login        = isset( $_POST['ap_login'] ) ? sanitize_user( wp_unslash( $_POST['ap_login'] ), true ) : '';
		$name         = isset( $_POST['ap_name'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_name'] ) ) : '';
		self::$values = array(
			'ap_login' => $login,
			'ap_name'  => $name,
			'ap_email' => isset( $_POST['ap_email'] ) ? sanitize_email( wp_unslash( $_POST['ap_email'] ) ) : '',
		);
		if ( isset( $_POST['ap_record'], $_POST['ap_token'] ) ) {
			$result       = Membership::redeem( absint( $_POST['ap_record'] ), sanitize_text_field( wp_unslash( $_POST['ap_token'] ) ), $login, $name );
			self::$notice = ! is_wp_error( $result ) && 'pending_review' === $result['state'] ? __( 'Your email is verified. The site owner will review your request. You will receive a password setup email if approved.', 'assemblypress' ) : __( 'Your membership is ready. Check your email to set your password, then sign in.', 'assemblypress' );
		} else {
			$email        = isset( $_POST['ap_email'] ) ? sanitize_email( wp_unslash( $_POST['ap_email'] ) ) : '';
			$result       = isset( $_POST['ap_recover'] ) ? Membership::recover( $email ) : Membership::apply( $email, $login, $name );
			self::$notice = __( 'If these details can be registered, an email will arrive with the next step. Already have an account? Use the sign-in or password reset link below.', 'assemblypress' );
		}
		if ( is_wp_error( $result ) ) {
			self::$notice = $result->get_error_message();
			self::$error  = true;
		}
	}

	/** Render the registration or confirmation form.
	 *
	 * @return string
	 */
	public static function render() {
		$output = '<div ' . get_block_wrapper_attributes() . '>';
		if ( self::$notice ) {
			$output .= '<p role="' . ( self::$error ? 'alert' : 'status' ) . '">' . esc_html( self::$notice ) . '</p>';
			if ( ! self::$error ) {
				return $output . self::account_links() . '</div>';
			}
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- These GET values only render a confirmation form; redemption requires POST and nonce.
		$id    = isset( $_GET['ap_membership'] ) ? absint( $_GET['ap_membership'] ) : 0;
		$token = isset( $_GET['ap_token'] ) ? sanitize_text_field( wp_unslash( $_GET['ap_token'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$data = $id ? Membership::get( $id ) : null;
		if ( $id && ( is_wp_error( $data ) || ! Membership::valid_token( $data, $token ) ) ) {
			return $output . '<p>' . esc_html__( 'This link has expired or has already been used. Contact the site owner for a new link.', 'assemblypress' ) . '</p>' . self::account_links() . '</div>';
		}
		$mode = Settings::get()['joining'];
		if ( ! $id && ! in_array( $mode, array( 'open', 'approval' ), true ) ) {
			return $output . '<p>' . esc_html__( 'Joining is by invitation. Contact the site owner for access.', 'assemblypress' ) . '</p>' . self::account_links() . '</div>';
		}
		$output .= '<form method="post">';
		if ( ! $id || 'invited' === $data['state'] ) {
			$output .= '<p>' . esc_html__( 'After your email is verified and any required approval is complete, we will email you a link to set your password.', 'assemblypress' ) . '</p>';
			if ( ! $id ) {
				$output .= self::input( 'ap_email', __( 'Email address', 'assemblypress' ), 'email' );
			}
			$output .= self::input( 'ap_login', __( 'Username', 'assemblypress' ), 'text' );
			$output .= self::input( 'ap_name', __( 'Display name', 'assemblypress' ), 'text' );
		}
		if ( $id ) {
			$output .= '<input type="hidden" name="ap_record" value="' . esc_attr( $id ) . '"><input type="hidden" name="ap_token" value="' . esc_attr( $token ) . '">';
		}
		$output .= '<div hidden><label>Website<input name="ap_website" tabindex="-1" autocomplete="off"></label></div>';
		$output .= wp_nonce_field( 'assemblypress_join', 'assemblypress_join_nonce', false, false );
		$output .= '<button type="submit">' . esc_html( $id ? __( 'Confirm membership', 'assemblypress' ) : ( 'approval' === $mode ? __( 'Request membership', 'assemblypress' ) : __( 'Join', 'assemblypress' ) ) ) . '</button></form>';
		if ( ! $id ) {
			$output .= '<details><summary>' . esc_html__( 'Need a new verification email?', 'assemblypress' ) . '</summary><form method="post">' . self::input( 'ap_email', __( 'Email address', 'assemblypress' ), 'email' ) . wp_nonce_field( 'assemblypress_join', 'assemblypress_join_nonce', false, false ) . '<button type="submit" name="ap_recover" value="1">' . esc_html__( 'Send new link', 'assemblypress' ) . '</button></form></details>';
		}
		return $output . self::account_links() . '</div>';
	}

	/** Accessible input.
	 *
	 * @param string $name Input name.
	 * @param string $label Label.
	 * @param string $type Input type.
	 * @return string
	 */
	private static function input( $name, $label, $type ) {
		$id = wp_unique_id( $name );
		return '<p><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label><br><input required id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" type="' . esc_attr( $type ) . '" value="' . esc_attr( self::$values[ $name ] ?? '' ) . '" maxlength="' . ( 'ap_login' === $name ? '60' : '100' ) . '"></p>';
	}

	/** Standard account destinations.
	 *
	 * @return string
	 */
	private static function account_links() {
		return '<p><a href="' . esc_url( wp_login_url( Settings::directory_url() ) ) . '">' . esc_html__( 'Sign in', 'assemblypress' ) . '</a> · <a href="' . esc_url( wp_lostpassword_url() ) . '">' . esc_html__( 'Reset password', 'assemblypress' ) . '</a></p>';
	}
}
