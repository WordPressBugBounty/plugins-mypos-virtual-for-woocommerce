<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
	</div>
	<?php
	/**
	 * Fires in the login page footer before closing the body tag.
	 *
	 * @since 4.6.0
	 */
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core login hook
	do_action( 'login_footer' );
	?>
</body>
</html>
