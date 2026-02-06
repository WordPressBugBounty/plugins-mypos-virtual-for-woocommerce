<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require 'mypos-page-functions.php';
require 'mypos-formatting-functions.php';

/**
 * Define a constant if it is not already defined.
 *
 * @param string $name Constant name.
 * @param mixed $value Value.
 */
function mypos_maybe_define_constant( $name, $value ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
	if ( ! defined( $name ) ) {
		define( $name, $value ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
	}
}

/**
 * Get other templates (e.g. product attributes) passing attributes and including the file.
 *
 * @param string $template_name Template name.
 * @param array $args Arguments. (default: array).
 * @param string $template_path Template path. (default: '').
 * @param string $default_path Default path. (default: '').
 */
function mypos_get_template( $template_name, $args = array(), $template_path = '', $default_path = '' ) {
	$cache_key = sanitize_key( implode( '-', array( 'template', $template_name, $template_path, $default_path ) ) );
	$template  = (string) wp_cache_get( $cache_key, 'mypos' );

	if ( ! $template ) {
		$template = mypos_locate_template( $template_name, $template_path, $default_path );

		// Don't cache the absolute path so that it can be shared between web servers with different paths.
		$cache_path = mypos_tokenize_path( $template, mypos_get_path_define_tokens() );

		mypos_set_template_cache( $cache_key, $cache_path );
	} else {
		// Make sure that the absolute path to the template is resolved.
		$template = mypos_untokenize_path( $template, mypos_get_path_define_tokens() );
	}

	$action_args = array(
		'template_name' => $template_name,
		'template_path' => $template_path,
		'located'       => $template,
		'args'          => $args,
	);

	// Validate template file exists and is readable before including.
	$validated_template = realpath( $action_args['located'] );
	if ( ! $validated_template || ! file_exists( $validated_template ) || ! is_readable( $validated_template ) ) {
		_doing_it_wrong( __FUNCTION__, sprintf( 'Template file %s does not exist or is not readable.', esc_html( $action_args['located'] ) ), '1.0.0' );
		return;
	}

	// Security: Ensure the template file is within allowed directories (plugin or theme).
	$plugin_path       = realpath( ( new MyPOS() )->plugin_path() );
	$theme_path        = realpath( get_stylesheet_directory() );
	$parent_theme_path = realpath( get_template_directory() );

	$is_valid_path = (
		( $plugin_path && 0 === strpos( $validated_template, $plugin_path ) ) ||
		( $theme_path && 0 === strpos( $validated_template, $theme_path ) ) ||
		( $parent_theme_path && 0 === strpos( $validated_template, $parent_theme_path ) )
	);

	if ( ! $is_valid_path ) {
		_doing_it_wrong( __FUNCTION__, 'Template file is outside of allowed directories.', '1.0.0' );
		return;
	}

	/**
	 * Action hook before including a myPOS template part.
	 *
	 * @param string $template_name The template name.
	 * @param string $template_path The template path.
	 * @param string $located The located template file.
	 * @param array $args Arguments passed to the template.
	 */
	do_action(
		'mypos_before_template_part',
		$action_args['template_name'],
		$action_args['template_path'],
		$action_args['located'],
		$action_args['args']
	);

	// Security: Make args available to template in a controlled manner.
	// We use a closure to limit variable scope and prevent pollution of the global namespace.
	call_user_func(
		function () use ( $validated_template, $args ) {
			// Final security check: Ensure file still exists and is readable at include time.
			if ( ! is_file( $validated_template ) || ! is_readable( $validated_template ) ) {
				return;
			}

			// Security: Validate file extension to ensure only PHP templates are included.
			$allowed_extensions = array( '.php' );
			$file_extension     = strtolower( substr( $validated_template, strrpos( $validated_template, '.' ) ) );
			if ( ! in_array( $file_extension, $allowed_extensions, true ) ) {
				return;
			}

			// Security: Additional validation - ensure no null bytes in path.
			if ( false !== strpos( $validated_template, "\0" ) ) {
				return;
			}

			// Security: Final whitelist check - ensure path matches expected template directories.
			$plugin_path       = realpath( ( new MyPOS() )->plugin_path() );
			$theme_path        = realpath( get_stylesheet_directory() );
			$parent_theme_path = realpath( get_template_directory() );

			$is_in_allowed_directory = false;
			if ( $plugin_path && 0 === strpos( $validated_template, $plugin_path ) ) {
				$is_in_allowed_directory = true;
			} elseif ( $theme_path && 0 === strpos( $validated_template, $theme_path ) ) {
				$is_in_allowed_directory = true;
			} elseif ( $parent_theme_path && 0 === strpos( $validated_template, $parent_theme_path ) ) {
				$is_in_allowed_directory = true;
			}

			if ( ! $is_in_allowed_directory ) {
				return;
			}

			// Make template arguments available to the included file.
			// This is safer than extract() as we're in a limited scope.
			if ( ! empty( $args ) && is_array( $args ) ) {
				// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- Template variable extraction; using EXTR_SKIP to prevent variable overwriting.
				extract( $args, EXTR_SKIP );
			}

			// Include the validated template file within this isolated scope.
			// Security: This file path has been validated through:
			// 1. realpath() canonicalization to resolve symlinks and validate existence
			// 2. Directory whitelist enforcement (twice) - only plugin/theme directories allowed
			// 3. File existence and readability verification (twice)
			// 4. File extension validation - only .php files permitted
			// 5. Null byte injection protection
			// 6. Path sanitization to remove directory traversal sequences
			// The variable $validated_template contains only a trusted, fully validated file path.
			// @phpstan-ignore-next-line -- File path fully validated, see security checks above
			include_once $validated_template; // nosemgrep: php.lang.security.file.inclusion-arg
		}
	);

	/**
	 * Action hook after including a myPOS template part.
	 *
	 * @param string $template_name The template name.
	 * @param string $template_path The template path.
	 * @param string $located The located template file.
	 * @param array $args Arguments passed to the template.
	 */
	do_action(
		'mypos_after_template_part',
		$action_args['template_name'],
		$action_args['template_path'],
		$action_args['located'],
		$action_args['args']
	);
}


/**
 * Locate a template and return the path for inclusion.
 *
 * This is the load order:
 *
 * yourtheme/$template_path/$template_name
 * yourtheme/$template_name
 * $default_path/$template_name
 *
 * @param string $template_name Template name.
 * @param string $template_path Template path. (default: '').
 * @param string $default_path Default path. (default: '').
 * @return string
 */
function mypos_locate_template( $template_name, $template_path = '', $default_path = '' ) {
	// Security: Sanitize template name to prevent directory traversal.
	$template_name = str_replace( array( '../', '..\\', '\0' ), '', $template_name );
	$template_name = ltrim( $template_name, '/' );

	if ( ! $template_path ) {
		$template_path = ( new MyPOS() )->template_path();
	}

	if ( ! $default_path ) {
		$default_path = ( new MyPOS() )->plugin_path() . '/templates/';
	}

	// Try to locate template in theme.
	$template = locate_template(
		array(
			trailingslashit( $template_path ) . $template_name,
			$template_name,
		)
	);

	// Get default template from plugin.
	if ( ! $template || ( defined( 'WC_TEMPLATE_DEBUG_MODE' ) && WC_TEMPLATE_DEBUG_MODE ) ) {
		$template = $default_path . $template_name;
	}

	// Security: Validate the final template path.
	$template = realpath( $template );
	if ( ! $template ) {
		return '';
	}

	return $template;
}

/**
 * Given a path, this will convert any of the subpaths into their corresponding tokens.
 *
 * @param string $path The absolute path to tokenize.
 * @param array $path_tokens An array keyed with the token, containing paths that should be replaced.
 * @return string The tokenized path.
 */
function mypos_tokenize_path( $path, $path_tokens ) {
	// Order most to least specific so that the token can encompass as much of the path as possible.
	uasort(
		$path_tokens,
		function ( $a, $b ) {
			$a = strlen( $a );
			$b = strlen( $b );

			if ( $a > $b ) {
				return -1;
			}

			if ( $b > $a ) {
				return 1;
			}

			return 0;
		}
	);

	foreach ( $path_tokens as $token => $token_path ) {
		if ( 0 !== strpos( $path, $token_path ) ) {
			continue;
		}

		$path = str_replace( $token_path, '{{' . $token . '}}', $path );
	}

	return $path;
}

/**
 * Given a tokenized path, this will expand the tokens to their full path.
 *
 * @param string $path The absolute path to expand.
 * @param array $path_tokens An array keyed with the token, containing paths that should be expanded.
 * @return string The absolute path.
 */
function mypos_untokenize_path( $path, $path_tokens ) {
	foreach ( $path_tokens as $token => $token_path ) {
		$path = str_replace( '{{' . $token . '}}', $token_path, $path );
	}

	return $path;
}

/**
 * Fetches an array containing all of the configurable path constants to be used in tokenization.
 *
 * @return array The key is the define and the path is the constant.
 */
function mypos_get_path_define_tokens() {
	$defines = array(
		'ABSPATH',
		'WP_CONTENT_DIR',
		'WP_PLUGIN_DIR',
		'WPMU_PLUGIN_DIR',
		'PLUGINDIR',
		'WP_THEME_DIR',
	);

	$path_tokens = array();
	foreach ( $defines as $define ) {
		if ( defined( $define ) ) {
			$path_tokens[ $define ] = constant( $define );
		}
	}

	/**
	 * Filter to allow modification of path define tokens for myPOS.
	 *
	 * @param array $path_tokens The path tokens.
	 * @return array
	 */
	return apply_filters( 'mypos_get_path_define_tokens', $path_tokens );
}

/**
 * Add a template to the template cache.
 *
 * @param string $cache_key Object cache key.
 * @param string $template Located template.
 */
function mypos_set_template_cache( $cache_key, $template ) {
	wp_cache_set( $cache_key, $template, 'mypos' );

	$cached_templates = wp_cache_get( 'cached_templates', 'mypos' );
	if ( is_array( $cached_templates ) ) {
		$cached_templates[] = $cache_key;
	} else {
		$cached_templates = array( $cache_key );
	}

	wp_cache_set( 'cached_templates', $cached_templates, 'mypos' );
}
