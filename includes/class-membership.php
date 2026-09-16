<?php
/**
 * Email-verified joining, owner invitations, and membership requests.
 *
 * @package AssemblyPress
 */

namespace AssemblyPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Membership lifecycle. Pending records never grant WordPress access. */
final class Membership {
	/** Register private records.
	 *
	 * @return void
	 */
	public static function register() {
		register_post_type(
			'ap_membership',
			array(
				'public'   => false,
				'show_ui'  => false,
				'supports' => array( 'title' ),
				'rewrite'  => false,
			)
		);
	}

	/** Read a record.
	 *
	 * @param int $id Record ID.
	 * @return array|\WP_Error
	 */
	public static function get( $id ) {
		if ( 'ap_membership' !== get_post_type( $id ) ) {
			return new \WP_Error( 'missing_membership', __( 'Membership record not found.', 'assemblypress' ), array( 'status' => 404 ) );
		}
		return array_merge( array( 'id' => (int) $id ), (array) get_post_meta( $id, '_ap_record', true ) );
	}

	/** Create a private record.
	 *
	 * @param array $data Record data.
	 * @return int|\WP_Error
	 */
	private static function create( $data ) {
		return wp_insert_post(
			array(
				'post_type'   => 'ap_membership',
				'post_status' => 'private',
				'post_title'  => $data['email'],
				'meta_input'  => array(
					'_ap_record' => $data,
					'_ap_state'  => $data['state'],
				),
			),
			true
		);
	}

	/** Save record data.
	 *
	 * @param int   $id Record ID.
	 * @param array $data Record data.
	 * @return void
	 */
	private static function save( $id, $data ) {
		unset( $data['id'] );
		update_post_meta( $id, '_ap_record', $data );
		update_post_meta( $id, '_ap_state', $data['state'] );
	}

	/** Issue an expiring token and email its URL. Only hashes are stored.
	 *
	 * @param int    $id Record ID.
	 * @param string $purpose Token purpose.
	 * @return true|\WP_Error
	 */
	public static function send_token( $id, $purpose ) {
		$data = self::get( $id );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$limit_key = 'ap_mail_' . hash_hmac( 'sha256', strtolower( $data['email'] ), wp_salt() );
		$count     = (int) get_transient( $limit_key );
		if ( $count >= 5 ) {
			return new \WP_Error( 'mail_limited', __( 'Too many emails have been requested for this address. Please try again in an hour.', 'assemblypress' ), array( 'status' => 429 ) );
		}
		set_transient( $limit_key, $count + 1, HOUR_IN_SECONDS );
		$token              = wp_generate_password( 40, false, false );
		$data['token_hash'] = wp_hash_password( $token );
		$data['expires']    = time() + ( 'invite' === $purpose ? 7 * DAY_IN_SECONDS : DAY_IN_SECONDS );
		self::save( $id, $data );
		$url     = add_query_arg(
			array(
				'ap_membership' => $id,
				'ap_token'      => $token,
				'ap_action'     => $purpose,
			),
			home_url( '/' )
		);
		$subject = 'invite' === $purpose ? __( 'You are invited to join', 'assemblypress' ) : __( 'Verify your email address', 'assemblypress' );
		/* translators: %s: Secure membership link. */
		$message           = sprintf( __( "Open this link to continue joining:\n\n%s\n\nIf you did not request this, you can ignore this email. The link expires and can only be used once.", 'assemblypress' ), $url );
		$sent              = wp_mail( $data['email'], $subject, $message );
		$data['mail_sent'] = (bool) $sent;
		self::save( $id, $data );
		return $sent ? true : new \WP_Error( 'mail_failed', __( 'The email could not be sent. Please try again or contact the site owner.', 'assemblypress' ), array( 'status' => 503 ) );
	}

	/** Invite a member. Called only by the authorized admin controller.
	 *
	 * @param string $email Email address.
	 * @return array|\WP_Error
	 */
	public static function invite( $email ) {
		$email = sanitize_email( $email );
		if ( ! is_email( $email ) || email_exists( $email ) ) {
			return new \WP_Error( 'invalid_email', __( 'Enter an email address that does not already have an account.', 'assemblypress' ), array( 'status' => 400 ) );
		}
		$pending = self::pending( $email );
		if ( $pending ) {
			return new \WP_Error( 'existing_request', __( 'This email already has a pending invitation or request. Find it in the list to send a new link or review it.', 'assemblypress' ), array( 'status' => 409 ) );
		}
		$id = self::create(
			array(
				'email'   => $email,
				'state'   => 'invited',
				'kind'    => 'invitation',
				'created' => time(),
			)
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		$result = self::send_token( $id, 'invite' );
		return is_wp_error( $result ) ? $result : self::public_record( self::get( $id ) );
	}

	/** Find a pending record for an email without exposing it publicly.
	 *
	 * @param string $email Email address.
	 * @return int
	 */
	private static function pending( $email ) {
		$query = new \WP_Query(
			array(
				'post_type'      => 'ap_membership',
				'post_status'    => 'private',
				'title'          => $email,
				'posts_per_page' => 1,
				'fields'         => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Exact email lookup, bounded to one pending record.
				'meta_query'     => array(
					array(
						'key'     => '_ap_state',
						'value'   => array( 'invited', 'pending_email', 'pending_review', 'approved' ),
						'compare' => 'IN',
					),
				),
			)
		); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Exact email and bounded state lookup.
		return $query->posts ? (int) $query->posts[0] : 0;
	}

	/** Request another email without revealing membership state.
	 *
	 * @param string $email Email address.
	 * @return true
	 */
	public static function recover( $email ) {
		$id = self::pending( sanitize_email( $email ) );
		if ( $id ) {
			$data = self::get( $id );
			if ( in_array( $data['state'], array( 'invited', 'pending_email' ), true ) ) {
				self::transition( $id, 'resend' );
			}
		}
		return true;
	}

	/** Submit a registration without creating an account before verification.
	 *
	 * @param string $email Email address.
	 * @param string $login Requested username.
	 * @param string $name Display name.
	 * @return true|\WP_Error
	 */
	public static function apply( $email, $login, $name ) {
		$mode = Settings::get()['joining'];
		if ( ! in_array( $mode, array( 'open', 'approval' ), true ) ) {
			return new \WP_Error( 'joining_closed', __( 'Registration is not open. Contact the site owner for access.', 'assemblypress' ) );
		}
		if ( ! is_email( $email ) || ! validate_username( $login ) || '' === $login || strlen( $login ) > 60 || '' === trim( $name ) ) {
			return new \WP_Error( 'invalid_registration', __( 'Enter a valid email, username, and display name.', 'assemblypress' ) );
		}
		// Do not disclose whether an email or username already has an account.
		if ( email_exists( $email ) || username_exists( $login ) || self::pending( sanitize_email( $email ) ) ) {
			return true;
		}
		$id = self::create(
			array(
				'email'             => sanitize_email( $email ),
				'login'             => sanitize_user( $login, true ),
				'name'              => sanitize_text_field( $name ),
				'state'             => 'pending_email',
				'kind'              => 'registration',
				'approval_required' => 'approval' === $mode,
				'created'           => time(),
			)
		);
		return is_wp_error( $id ) ? $id : self::send_token( $id, 'activate' );
	}

	/** Verify token for the current record state.
	 *
	 * @param array  $data Record.
	 * @param string $token Raw email token.
	 * @return bool
	 */
	public static function valid_token( $data, $token ) {
		return ! empty( $data['token_hash'] ) && ! empty( $data['expires'] ) && $data['expires'] >= time() && wp_check_password( $token, $data['token_hash'] );
	}

	/** Activate email or accept invitation under a per-record lock.
	 *
	 * @param int    $id Record ID.
	 * @param string $token Email token.
	 * @param string $login Invitation username.
	 * @param string $name Invitation display name.
	 * @return array|\WP_Error
	 */
	public static function redeem( $id, $token, $login = '', $name = '' ) {
		return self::locked(
			$id,
			static function ( $data ) use ( $id, $token, $login, $name ) {
				if ( ! in_array( $data['state'], array( 'invited', 'pending_email' ), true ) || ! self::valid_token( $data, $token ) ) {
					return new \WP_Error( 'invalid_token', __( 'This link has expired or has already been used. Ask the site owner to send a new link.', 'assemblypress' ) );
				}
				if ( 'invited' === $data['state'] ) {
					if ( ! validate_username( $login ) || '' === $login || strlen( $login ) > 60 || username_exists( $login ) || '' === trim( $name ) ) {
						return new \WP_Error( 'invalid_username', __( 'Choose an available username and enter your display name.', 'assemblypress' ) );
					}
					$data['login'] = sanitize_user( $login, true );
					$data['name']  = sanitize_text_field( $name );
				}
				$data['verified'] = true;
				$data['state']    = ! empty( $data['approval_required'] ) ? 'pending_review' : 'approved';
				unset( $data['token_hash'], $data['expires'] );
				self::save( $id, $data );
				if ( 'pending_review' === $data['state'] ) {
					wp_mail( get_option( 'admin_email' ), __( 'A membership request is ready for review', 'assemblypress' ), admin_url( 'admin.php?page=assemblypress-membership' ) );
				}
				return 'approved' === $data['state'] ? self::provision( $id, $data ) : self::public_record( self::get( $id ) );
			}
		);
	}

	/** Serialize lifecycle mutations.
	 *
	 * @param int      $id Record ID.
	 * @param callable $callback Transition callback.
	 * @return mixed
	 */
	private static function locked( $id, $callback ) {
		$key = 'assemblypress_membership_lock_' . absint( $id );
		if ( ! add_option( $key, time(), '', false ) ) {
			return new \WP_Error( 'membership_busy', __( 'This request is being processed. Please try again.', 'assemblypress' ), array( 'status' => 409 ) );
		}
		try {
			$data = self::get( $id );
			return is_wp_error( $data ) ? $data : $callback( $data );
		} finally {
			delete_option( $key );
		}
	}

	/** Perform an owner transition.
	 *
	 * @param int    $id Record ID.
	 * @param string $action Requested action.
	 * @return array|\WP_Error
	 */
	public static function transition( $id, $action ) {
		return self::locked(
			$id,
			static function ( $data ) use ( $id, $action ) {
				if ( 'resend' === $action && in_array( $data['state'], array( 'invited', 'pending_email' ), true ) ) {
					$result = self::send_token( $id, 'invited' === $data['state'] ? 'invite' : 'activate' );
					return is_wp_error( $result ) ? $result : self::public_record( self::get( $id ) );
				}
				if ( 'approve' === $action && in_array( $data['state'], array( 'pending_review', 'approved' ), true ) && ! empty( $data['verified'] ) ) {
					$data['state'] = 'approved';
					self::save( $id, $data );
					return self::provision( $id, $data );
				}
				if ( ( 'reject' === $action && in_array( $data['state'], array( 'pending_review', 'approved' ), true ) ) || ( 'revoke' === $action && in_array( $data['state'], array( 'invited', 'pending_email' ), true ) ) ) {
					$data['state'] = 'reject' === $action ? 'rejected' : 'revoked';
					unset( $data['token_hash'], $data['expires'] );
					self::save( $id, $data );
					if ( 'rejected' === $data['state'] ) {
						wp_mail( $data['email'], __( 'Membership request update', 'assemblypress' ), __( 'Your membership request was not approved. Contact the site owner if you have questions.', 'assemblypress' ) );
					}
					return self::public_record( self::get( $id ) );
				}
				return new \WP_Error( 'invalid_transition', __( 'This action is not available for the current membership state.', 'assemblypress' ), array( 'status' => 409 ) );
			}
		);
	}

	/** Create the WordPress identity only after verification and approval.
	 *
	 * @param int   $id Record ID.
	 * @param array $data Verified data.
	 * @return array|\WP_Error
	 */
	private static function provision( $id, $data ) {
		$keys = array( 'ap_identity_' . hash( 'sha256', strtolower( $data['email'] ) ), 'ap_login_' . hash( 'sha256', strtolower( $data['login'] ) ) );
		$held = array();
		foreach ( $keys as $key ) {
			if ( ! add_option( $key, time(), '', false ) ) {
				foreach ( $held as $held_key ) {
					delete_option( $held_key ); }
				return new \WP_Error( 'account_busy', __( 'Account creation is in progress. Ask the site owner to retry approval.', 'assemblypress' ), array( 'status' => 409 ) );
			}
			$held[] = $key;
		}
		try {
			$user_id = wp_insert_user(
				array(
					'user_login'   => $data['login'],
					'user_email'   => $data['email'],
					'display_name' => $data['name'],
					'user_pass'    => wp_generate_password( 32, true ),
					'role'         => 'subscriber',
				)
			);
			if ( is_wp_error( $user_id ) ) {
				return new \WP_Error( 'account_conflict', __( 'The account could not be created. Contact the site owner to resolve the username or email conflict.', 'assemblypress' ), array( 'status' => 409 ) );
			}
			$data['state']   = 'active';
			$data['user_id'] = $user_id;
			self::save( $id, $data );
			wp_new_user_notification( $user_id, null, 'user' );
			return self::public_record( self::get( $id ) );
		} finally {
			foreach ( $held as $held_key ) {
				delete_option( $held_key ); }
		}
	}

	/** Omit secrets from admin responses too.
	 *
	 * @param array $data Record.
	 * @return array
	 */
	public static function public_record( $data ) {
		unset( $data['token_hash'] );
		$data['expired'] = isset( $data['expires'] ) && $data['expires'] < time();
		return $data;
	}
}
