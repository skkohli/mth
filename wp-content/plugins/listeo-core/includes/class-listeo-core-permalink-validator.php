<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Validator for Listeo Custom Permalink Structures
 * 
 * Validates permalink structures and ensures they are safe and functional
 *
 * @package listeo-core
 * @since 1.9.51
 */
class Listeo_Core_Permalink_Validator {
	/**
	 * Valid tokens that are supported
	 *
	 * @var array
	 */
	private $valid_tokens = array(
		'listing',
		'listing_category',
		'region',
		'listing_id',
		'listing_type',
		'year',
		'monthnum',
		'author',
	);

	/**
	 * Required tokens that must be present
	 *
	 * @var array
	 */
	private $required_tokens = array(
		'listing',
	);

	/**
	 * Validate a permalink structure
	 *
	 * @param string $structure The permalink structure to validate
	 * @return bool|array True if valid, array of error messages if invalid
	 */
	public function validate_structure( $structure ) {
		$errors = array();

		// Basic validation
		if ( empty( $structure ) ) {
			$errors[] = __( 'Permalink structure cannot be empty.', 'listeo-core' );
			return $errors;
		}

		// Check for required tokens
		$required_errors = $this->validate_required_tokens( $structure );
		$errors = array_merge( $errors, $required_errors );

		// Check for invalid characters
		$character_errors = $this->validate_characters( $structure );
		$errors = array_merge( $errors, $character_errors );

		// Check token syntax
		$token_errors = $this->validate_token_syntax( $structure );
		$errors = array_merge( $errors, $token_errors );

		// Check for valid tokens only
		$valid_token_errors = $this->validate_token_existence( $structure );
		$errors = array_merge( $errors, $valid_token_errors );

		// Check structure format
		$format_errors = $this->validate_structure_format( $structure );
		$errors = array_merge( $errors, $format_errors );

		// Check for potential conflicts with WordPress
		$conflict_errors = $this->validate_wordpress_conflicts( $structure );
		$errors = array_merge( $errors, $conflict_errors );

		// Check for conflicting token combinations
		$token_conflict_errors = $this->validate_token_conflicts( $structure );
		$errors = array_merge( $errors, $token_conflict_errors );

		return empty( $errors ) ? true : array_unique( $errors );
	}

	/**
	 * Validate required tokens are present
	 *
	 * @param string $structure The permalink structure
	 * @return array Array of error messages
	 */
	private function validate_required_tokens( $structure ) {
		$errors = array();

		foreach ( $this->required_tokens as $required_token ) {
			$token = '%' . $required_token . '%';
			if ( strpos( $structure, $token ) === false ) {
				$errors[] = sprintf(
					/* translators: %s: required token */
					__( 'Structure must contain the required token: %s', 'listeo-core' ),
					$token
				);
			}
		}

		return $errors;
	}

	/**
	 * Validate characters in structure
	 *
	 * @param string $structure The permalink structure
	 * @return array Array of error messages
	 */
	private function validate_characters( $structure ) {
		$errors = array();

		// Invalid characters for URLs
		$invalid_chars = array( '<', '>', '"', "'", '|', '?', '*', '\\' );
		
		foreach ( $invalid_chars as $char ) {
			if ( strpos( $structure, $char ) !== false ) {
				$errors[] = sprintf(
					/* translators: %s: invalid character */
					__( 'Structure contains invalid character: %s', 'listeo-core' ),
					$char
				);
			}
		}

		// Check for spaces (should use dashes instead)
		if ( preg_match( '/\s/', $structure ) ) {
			$errors[] = __( 'Structure should not contain spaces. Use dashes (-) instead.', 'listeo-core' );
		}

		return $errors;
	}

	/**
	 * Validate token syntax
	 *
	 * @param string $structure The permalink structure
	 * @return array Array of error messages
	 */
	private function validate_token_syntax( $structure ) {
		$errors = array();

		// Check for malformed tokens (unmatched % signs)
		$percent_count = substr_count( $structure, '%' );
		if ( $percent_count % 2 !== 0 ) {
			$errors[] = __( 'Structure contains unmatched % characters. Tokens must be wrapped in % signs.', 'listeo-core' );
		}

		// Check for empty tokens (%%)
		if ( strpos( $structure, '%%' ) !== false ) {
			$errors[] = __( 'Structure contains empty tokens (%%). Remove empty token placeholders.', 'listeo-core' );
		}

		// Check for properly formed tokens
		// Extract all tokens and validate each one individually
		preg_match_all( '/%([^%]+)%/', $structure, $matches );
		
		if ( ! empty( $matches[1] ) ) {
			foreach ( $matches[1] as $token_content ) {
				// Check if token content is valid (no spaces, special chars except underscore)
				if ( ! preg_match( '/^[a-zA-Z][a-zA-Z0-9_]*$/', $token_content ) ) {
					$errors[] = sprintf( __( 'Invalid token format: %s. Tokens should contain only letters, numbers, and underscores.', 'listeo-core' ), '%' . $token_content . '%' );
				}
			}
		}
		
		// Check for malformed token patterns (consecutive % without proper token between)
		if ( preg_match( '/%\s*%/', $structure ) ) {
			$errors[] = __( 'Structure contains empty or malformed tokens.', 'listeo-core' );
		}

		return $errors;
	}

	/**
	 * Validate that all tokens exist and are supported
	 *
	 * @param string $structure The permalink structure
	 * @return array Array of error messages
	 */
	private function validate_token_existence( $structure ) {
		$errors = array();

		// Extract all tokens
		preg_match_all( '/%([^%]+)%/', $structure, $matches );
		
		if ( ! empty( $matches[1] ) ) {
			foreach ( $matches[1] as $token ) {
				if ( ! in_array( $token, $this->valid_tokens, true ) ) {
					$errors[] = sprintf(
						/* translators: %s: invalid token */
						__( 'Invalid token: %s. Supported tokens: %s', 'listeo-core' ),
						'%' . $token . '%',
						'%' . implode( '%, %', $this->valid_tokens ) . '%'
					);
				}
			}
		}

		return $errors;
	}

	/**
	 * Validate overall structure format
	 *
	 * @param string $structure The permalink structure
	 * @return array Array of error messages
	 */
	private function validate_structure_format( $structure ) {
		$errors = array();

		// Remove tokens for format checking
		$structure_without_tokens = preg_replace( '/%[^%]+%/', 'token', $structure );

		// Check for leading slash (not allowed)
		if ( strpos( $structure, '/' ) === 0 ) {
			$errors[] = __( 'Structure should not start with a slash (/).', 'listeo-core' );
		}

		// Check for trailing slash (will be handled automatically)
		if ( substr( $structure, -1 ) === '/' ) {
			// This is not an error, just a note for users
			// WordPress will handle trailing slashes based on permalink settings
		}

		// Check for multiple consecutive slashes
		if ( strpos( $structure_without_tokens, '//' ) !== false ) {
			$errors[] = __( 'Structure contains multiple consecutive slashes (//).', 'listeo-core' );
		}

		// Check minimum length
		if ( strlen( trim( $structure, '/' ) ) < 1 ) {
			$errors[] = __( 'Structure is too short.', 'listeo-core' );
		}

		// Check maximum reasonable length
		if ( strlen( $structure ) > 200 ) {
			$errors[] = __( 'Structure is too long. Keep it under 200 characters.', 'listeo-core' );
		}

		return $errors;
	}

	/**
	 * Check for potential conflicts with WordPress reserved words
	 *
	 * @param string $structure The permalink structure
	 * @return array Array of error messages
	 */
	private function validate_wordpress_conflicts( $structure ) {
		$errors = array();

		// WordPress reserved words that could cause conflicts
		$reserved_words = array(
			'wp-admin',
			'wp-content',
			'wp-includes',
			'admin',
			'login',
			'register',
			'dashboard',
			'api',
			'rest',
			'feed',
			'rss',
			'sitemap',
			'robots.txt',
			'favicon.ico',
			'get-ticket',  // Listeo QR code system hardcoded URL
		);

		// Remove tokens and get the static parts
		$static_parts = preg_replace( '/%[^%]+%/', '', $structure );
		$segments = explode( '/', trim( $static_parts, '/' ) );

		foreach ( $segments as $segment ) {
			$segment = trim( $segment );
			if ( ! empty( $segment ) && in_array( strtolower( $segment ), $reserved_words, true ) ) {
				$errors[] = sprintf(
					/* translators: %s: reserved word */
					__( 'Structure uses WordPress reserved word: %s. This may cause conflicts.', 'listeo-core' ),
					$segment
				);
			}
		}

		return $errors;
	}

	/**
	 * Validate that structure will work with existing Listeo features
	 *
	 * @param string $structure The permalink structure
	 * @return bool|array True if compatible, array of warnings if issues found
	 */
	public function validate_listeo_compatibility( $structure ) {
		$warnings = array();

		// Check compatibility with region_in_links setting
		if ( get_option( 'listeo_region_in_links' ) && strpos( $structure, '%region%' ) === false ) {
			$warnings[] = __( 'You have "Region in Links" enabled but your custom structure doesn\'t include %region%. This may cause conflicts.', 'listeo-core' );
		}

		// Check compatibility with combined taxonomy URLs
		if ( get_option( 'listeo_combined_taxonomy_urls' ) ) {
			$warnings[] = __( 'You have "Combined Taxonomy URLs" enabled. Custom permalinks will override this feature.', 'listeo-core' );
		}

		// Check if structure matches existing basic settings
		$permalink_settings = Listeo_Core_Post_Types::get_permalink_structure();
		$listing_base = ! empty( $permalink_settings['listing_base'] ) ? $permalink_settings['listing_base'] : 'listing';
		
		if ( strpos( $structure, $listing_base ) === 0 ) {
			$warnings[] = sprintf(
				/* translators: %s: listing base */
				__( 'Your custom structure starts with the listing base "%s". This may cause URL conflicts.', 'listeo-core' ),
				$listing_base
			);
		}

		return empty( $warnings ) ? true : $warnings;
	}

	/**
	 * Quick validation for AJAX requests
	 *
	 * @param string $structure The permalink structure
	 * @return array Simple validation result
	 */
	public function quick_validate( $structure ) {
		if ( empty( $structure ) ) {
			return array(
				'valid' => false,
				'message' => __( 'Structure cannot be empty', 'listeo-core' ),
			);
		}

		if ( strpos( $structure, '%listing%' ) === false ) {
			return array(
				'valid' => false,
				'message' => __( 'Structure must contain %listing%', 'listeo-core' ),
			);
		}

		// Check for basic syntax issues
		if ( substr_count( $structure, '%' ) % 2 !== 0 ) {
			return array(
				'valid' => false,
				'message' => __( 'Unmatched % characters', 'listeo-core' ),
			);
		}

		return array(
			'valid' => true,
			'message' => __( 'Structure looks valid', 'listeo-core' ),
		);
	}

	/**
	 * Check for conflicting token combinations
	 *
	 * @param string $structure The permalink structure
	 * @return array Array of error messages
	 */
	private function validate_token_conflicts( $structure ) {
		$errors = array();

		// Check for duplicate tokens which would definitely cause problems
		$tokens = array();
		preg_match_all('/%[^%]+%/', $structure, $matches);
		
		if (!empty($matches[0])) {
			$tokens = $matches[0];
			$token_counts = array_count_values($tokens);
			
			foreach ($token_counts as $token => $count) {
				if ($count > 1) {
					$errors[] = sprintf(
						__('Structure contains duplicate %s token which will cause conflicts.', 'listeo-core'),
						$token
					);
				}
			}
		}

		// For structures with %listing% and %listing_id%, no longer block them
		// Let users experiment and decide what works for their use case
		
		return $errors;
	}

	/**
	 * Sanitize a structure input
	 *
	 * @param string $structure The input structure
	 * @return string Sanitized structure
	 */
	public function sanitize_structure( $structure ) {
		// Basic sanitization
		$structure = sanitize_text_field( $structure );
		
		// Remove multiple slashes
		$structure = preg_replace( '#/+#', '/', $structure );
		
		// Remove leading/trailing slashes
		$structure = trim( $structure, '/' );
		
		// Ensure spaces become dashes in static parts (not in tokens)
		$structure = preg_replace_callback( '/([^%]*?)([^%]*?)/', function( $matches ) {
			// Only replace spaces with dashes outside of tokens
			if ( strpos( $matches[0], '%' ) === false ) {
				return str_replace( ' ', '-', $matches[0] );
			}
			return $matches[0];
		}, $structure );

		return $structure;
	}

	/**
	 * Get validation rules for JavaScript
	 *
	 * @return array Validation rules
	 */
	public function get_validation_rules_for_js() {
		return array(
			'required_tokens'    => $this->required_tokens,
			'valid_tokens'       => $this->valid_tokens,
			'invalid_chars'      => array( '<', '>', '"', "'", '|', '?', '*', '\\' ),
			'max_length'         => 200,
			'reserved_words'     => array( 'wp-admin', 'wp-content', 'admin', 'login', 'register' ),
		);
	}
}