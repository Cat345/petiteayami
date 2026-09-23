<?php
/**
 * PRO: keeps the plugin's robots.txt rules inside a PHYSICAL robots.txt file.
 *
 * WordPress serves its virtual robots.txt (and runs the robots_txt filter that
 * src/RobotsTxt.php hooks) only while no physical robots.txt exists in the
 * WordPress root. When a site has a physical file, the free version can only
 * show the rules and ask the user to paste them. This module writes them into
 * that file itself, inside a managed block:
 *
 *   # BEGIN Filter Everything
 *   ...
 *   # END Filter Everything
 *
 * via insert_with_markers() — the same way core maintains .htaccess — so the
 * rest of the file is never touched and the write is idempotent. The block is
 * re-synced whenever something that feeds the rules changes (plugin settings,
 * URL prefixes, Indexing Depth, SEO Rules, filters, Filter Sets, permalink mode,
 * plugin version) and once a day by cron as a safety net (a file restored from
 * a backup or rewritten by a robots.txt editor). Turning the option off or
 * deactivating the plugin removes the block.
 *
 * A site WITHOUT a physical file is left alone on purpose: creating one would
 * replace the virtual robots.txt and silently drop what other plugins add there
 * (sitemap lines etc.). The virtual path keeps working as in 1.9.6.
 *
 * Everything here is PRO-only; src/ code calls these functions only behind
 * function_exists() so the free build stays unaffected.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'FLRT_ROBOTS_FILE_MARKER' ) ) {
    define( 'FLRT_ROBOTS_FILE_MARKER', 'Filter Everything' );
}
if ( ! defined( 'FLRT_ROBOTS_FILE_STATE_OPTION' ) ) {
    define( 'FLRT_ROBOTS_FILE_STATE_OPTION', 'wpc_robots_file_state' );
}
if ( ! defined( 'FLRT_ROBOTS_FILE_CRON_HOOK' ) ) {
    define( 'FLRT_ROBOTS_FILE_CRON_HOOK', 'wpc_robots_file_daily_sync' );
}
if ( ! defined( 'FLRT_ROBOTS_FILE_ACTION' ) ) {
    define( 'FLRT_ROBOTS_FILE_ACTION', 'wpc_robots_file_sync' );
}

/**
 * Absolute path of the physical robots.txt this module manages.
 *
 * @return string
 */
function flrt_robots_file_path()
{
    return (string) apply_filters( 'wpc_robots_file_path', ABSPATH . 'robots.txt' );
}

/**
 * True when the plugin should keep its block in the physical file: the helper
 * is available, the option is on and there IS a physical file to manage.
 *
 * @return bool
 */
function flrt_robots_file_wanted()
{
    if ( ! function_exists( 'flrt_robots_helper_available' ) || ! flrt_robots_helper_available() ) {
        return false;
    }

    if ( flrt_get_option( 'robots_txt_block_filters' ) !== 'on' ) {
        return false;
    }

    return file_exists( flrt_robots_file_path() );
}

/**
 * Loads the core marker helpers (wp-admin/includes/misc.php) when a frontend,
 * cron or CLI request needs them.
 */
function flrt_robots_file_load_core()
{
    if ( ! function_exists( 'insert_with_markers' ) ) {
        require_once ABSPATH . 'wp-admin/includes/misc.php';
    }
}

/**
 * Reasons why the block cannot be written right now, keyed by a stable code.
 * Empty array = nothing blocks the write.
 *
 * @return array<string,string> code => human-readable explanation
 */
function flrt_robots_file_blockers()
{
    $reasons = [];
    $file    = flrt_robots_file_path();

    if ( is_multisite() && ! is_main_site() ) {
        $reasons['multisite'] = esc_html__( 'In a multisite network only the main site can manage the shared robots.txt file.', 'filter-everything' );
    }

    $home_path = trim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
    if ( $home_path !== '' ) {
        $reasons['home_subdirectory'] = esc_html__( 'Your site lives in a subdirectory, and crawlers read robots.txt only from the domain root. Add the rules to the robots.txt file at the root of the domain yourself.', 'filter-everything' );
    }

    $site_path = trim( (string) wp_parse_url( site_url( '/' ), PHP_URL_PATH ), '/' );
    if ( $site_path !== '' && $site_path !== $home_path ) {
        $reasons['core_subdirectory'] = esc_html__( 'WordPress core is installed in its own subdirectory, so the file next to it is not the robots.txt crawlers read. Add the rules to the robots.txt file at the root of the domain yourself.', 'filter-everything' );
    }

    if ( ! get_option( 'blog_public' ) ) {
        $reasons['not_public'] = esc_html__( 'Search engine visibility is discouraged in Settings → Reading, so the plugin does not touch robots.txt. It will write the rules as soon as the site is visible to search engines again.', 'filter-everything' );
    }

    if ( ! file_exists( $file ) ) {
        // Nothing to manage — the virtual robots.txt path applies.
        return $reasons;
    }

    if ( ! wp_is_writable( $file ) ) {
        $reasons['not_writable'] = sprintf(
            /* translators: %s: path to robots.txt */
            esc_html__( 'The file %s is not writable by PHP (file permissions or an open_basedir restriction). Make it writable and click Retry, or copy the rules into it yourself.', 'filter-everything' ),
            '<code>' . esc_html( $file ) . '</code>'
        );
    }

    return $reasons;
}

/**
 * Comment lines core prints right after "# BEGIN Filter Everything". Fixed
 * text (instead of core's localized sentence) so the stored block compares
 * equal regardless of the admin locale.
 *
 * @return string[]
 */
function flrt_robots_file_instructions()
{
    return [
        '# Written by the Filter Everything plugin (Filters → Settings → General → Crawlers and bots).',
        '# Changes made by hand between these markers are overwritten on the next sync.',
    ];
}

add_filter( 'insert_with_markers_inline_instructions', function ( $instructions, $marker ) {
    if ( $marker === FLRT_ROBOTS_FILE_MARKER ) {
        return flrt_robots_file_instructions();
    }
    return $instructions;
}, 10, 2 );

/**
 * The lines the managed block should contain right now (instructions + rules).
 *
 * @return string[] empty when there are no rules
 */
function flrt_robots_file_expected_lines()
{
    $rules = function_exists( 'flrt_robots_txt_rules' ) ? flrt_robots_txt_rules() : '';
    if ( $rules === '' ) {
        return [];
    }

    $lines = explode( "\n", str_replace( "\r\n", "\n", $rules ) );

    return array_merge( flrt_robots_file_instructions(), $lines );
}

/**
 * The lines currently between the markers in the file (empty when absent).
 *
 * @return string[]
 */
function flrt_robots_file_current_lines()
{
    $file = flrt_robots_file_path();
    if ( ! file_exists( $file ) || ! is_readable( $file ) ) {
        return [];
    }

    // Not extract_from_markers(): since WP 5.3 it drops every "#" line, and
    // half of our block is comments. Read the block exactly as written.
    $lines  = explode( "\n", str_replace( "\r\n", "\n", (string) file_get_contents( $file ) ) );
    $start  = '# BEGIN ' . FLRT_ROBOTS_FILE_MARKER;
    $end    = '# END ' . FLRT_ROBOTS_FILE_MARKER;
    $inside = false;
    $result = [];

    foreach ( $lines as $line ) {
        if ( strpos( $line, $end ) !== false ) {
            break;
        }
        if ( $inside ) {
            $result[] = $line;
        } elseif ( strpos( $line, $start ) !== false ) {
            $inside = true;
        }
    }

    return $result;
}

/**
 * True when the file has a "# BEGIN Filter Everything" block at all.
 *
 * @return bool
 */
function flrt_robots_file_has_block()
{
    $file = flrt_robots_file_path();
    if ( ! file_exists( $file ) || ! is_readable( $file ) ) {
        return false;
    }

    $content = (string) file_get_contents( $file );

    return strpos( $content, '# BEGIN ' . FLRT_ROBOTS_FILE_MARKER ) !== false;
}

/**
 * Last write result, stored so the settings page can say when the block was
 * last written and why the last attempt failed.
 *
 * @return array{status:string,reason:string,message:string,time:int,context:string}
 */
function flrt_robots_file_state()
{
    $defaults = [
        'status'  => 'never',   // never | synced | removed | error
        'reason'  => '',
        'message' => '',
        'time'    => 0,
        'context' => '',
    ];
    $state = get_option( FLRT_ROBOTS_FILE_STATE_OPTION, [] );

    return array_merge( $defaults, is_array( $state ) ? $state : [] );
}

function flrt_robots_file_set_state( $status, $context = '', $reason = '', $message = '' )
{
    update_option( FLRT_ROBOTS_FILE_STATE_OPTION, [
        'status'  => (string) $status,
        'reason'  => (string) $reason,
        'message' => (string) $message,
        'time'    => time(),
        'context' => (string) $context,
    ], false );
}

/**
 * Live verdict for the settings page. Reads the file, never writes it.
 *
 * @return array{code:string,message:string,blockers:array<string,string>,file:string}
 *   code: virtual | disabled | blocked | synced | stale
 */
function flrt_robots_file_check()
{
    $file   = flrt_robots_file_path();
    $result = [ 'code' => 'virtual', 'message' => '', 'blockers' => [], 'file' => $file ];

    if ( ! file_exists( $file ) ) {
        return $result;
    }

    if ( flrt_get_option( 'robots_txt_block_filters' ) !== 'on' ) {
        $result['code'] = 'disabled';
        return $result;
    }

    $blockers = flrt_robots_file_blockers();
    if ( ! empty( $blockers ) ) {
        $result['code']     = 'blocked';
        $result['blockers'] = $blockers;
        return $result;
    }

    $expected = flrt_robots_file_expected_lines();
    $current  = flrt_robots_file_current_lines();

    $result['code'] = ( $expected === $current ) ? 'synced' : 'stale';

    return $result;
}

/**
 * Removes the managed block from the file. Own implementation because
 * insert_with_markers() with an empty insertion leaves an empty block behind.
 *
 * @return bool true when the file no longer contains the block (or never did)
 */
function flrt_robots_file_remove( $context = 'remove' )
{
    $file = flrt_robots_file_path();

    if ( ! file_exists( $file ) || ! flrt_robots_file_has_block() ) {
        return true;
    }

    if ( ! wp_is_writable( $file ) ) {
        flrt_robots_file_set_state( 'error', $context, 'not_writable', esc_html__( 'The robots.txt file is not writable, so the Filter Everything block could not be removed.', 'filter-everything' ) );
        return false;
    }

    $fp = @fopen( $file, 'r+' );
    if ( ! $fp ) {
        flrt_robots_file_set_state( 'error', $context, 'open_failed', esc_html__( 'The robots.txt file could not be opened for writing.', 'filter-everything' ) );
        return false;
    }

    flock( $fp, LOCK_EX );

    $lines = [];
    while ( ! feof( $fp ) ) {
        $lines[] = rtrim( (string) fgets( $fp ), "\r\n" );
    }

    $start   = '# BEGIN ' . FLRT_ROBOTS_FILE_MARKER;
    $end     = '# END ' . FLRT_ROBOTS_FILE_MARKER;
    $kept    = [];
    $inside  = false;

    foreach ( $lines as $line ) {
        if ( ! $inside && strpos( $line, $start ) !== false ) {
            $inside = true;
            // Drop the blank separator the write added before the block.
            if ( ! empty( $kept ) && end( $kept ) === '' ) {
                array_pop( $kept );
            }
            continue;
        }
        if ( $inside ) {
            if ( strpos( $line, $end ) !== false ) {
                $inside = false;
            }
            continue;
        }
        $kept[] = $line;
    }

    // fgets() past the final newline yields one trailing '' — keep exactly one newline at EOF.
    while ( ! empty( $kept ) && end( $kept ) === '' ) {
        array_pop( $kept );
    }
    $data = empty( $kept ) ? '' : implode( "\n", $kept ) . "\n";

    fseek( $fp, 0 );
    $written = fwrite( $fp, $data );
    if ( $written !== false ) {
        ftruncate( $fp, $written );
    }
    fflush( $fp );
    flock( $fp, LOCK_UN );
    fclose( $fp );

    if ( function_exists( 'opcache_invalidate' ) ) {
        @opcache_invalidate( $file, true );
    }

    if ( $written === false ) {
        flrt_robots_file_set_state( 'error', $context, 'write_failed', esc_html__( 'Writing to the robots.txt file failed.', 'filter-everything' ) );
        return false;
    }

    flrt_robots_file_set_state( 'removed', $context );

    return true;
}

/**
 * Brings the physical file in line with the current settings: writes the
 * block when the option is on, removes it when the option is off. Compares
 * before writing, so calling it often is cheap.
 *
 * @param string $context what triggered the sync (for the stored state / debugging)
 * @return bool true when the file matches the desired state afterwards
 */
function flrt_robots_file_sync( $context = '' )
{
    if ( ! function_exists( 'flrt_robots_helper_available' ) || ! flrt_robots_helper_available() ) {
        return false;
    }

    $file = flrt_robots_file_path();

    // No physical file → WordPress serves the virtual one, nothing to manage.
    if ( ! file_exists( $file ) ) {
        return true;
    }

    if ( flrt_get_option( 'robots_txt_block_filters' ) !== 'on' ) {
        return flrt_robots_file_remove( $context );
    }

    $blockers = flrt_robots_file_blockers();
    if ( ! empty( $blockers ) ) {
        $reason = key( $blockers );
        flrt_robots_file_set_state( 'error', $context, $reason, reset( $blockers ) );
        return false;
    }

    $expected = flrt_robots_file_expected_lines();

    if ( empty( $expected ) ) {
        // No filters → no rules; leave nothing behind.
        return flrt_robots_file_remove( $context );
    }

    if ( $expected === flrt_robots_file_current_lines() ) {
        $state = flrt_robots_file_state();
        if ( $state['status'] !== 'synced' ) {
            flrt_robots_file_set_state( 'synced', $context );
        }
        return true;
    }

    flrt_robots_file_load_core();

    // insert_with_markers() wants the payload without the instruction lines —
    // it prepends them itself (see the inline-instructions filter above).
    $payload = array_slice( $expected, count( flrt_robots_file_instructions() ) );
    $ok      = insert_with_markers( $file, FLRT_ROBOTS_FILE_MARKER, $payload );

    if ( function_exists( 'opcache_invalidate' ) ) {
        @opcache_invalidate( $file, true );
    }

    if ( ! $ok ) {
        flrt_robots_file_set_state( 'error', $context, 'write_failed', esc_html__( 'Writing to the robots.txt file failed. Check the file permissions and click Retry.', 'filter-everything' ) );
        return false;
    }

    flrt_robots_file_set_state( 'synced', $context );

    do_action( 'wpc_robots_file_synced', $file, $context );

    return true;
}

/**
 * Queues one sync for the end of the request. Saving a Filter Set fires
 * save_post several times and the settings form updates several options in
 * a row — one deferred sync covers them all.
 */
function flrt_robots_file_queue( $context = '' )
{
    static $queued = false;

    if ( $queued ) {
        return;
    }
    $queued = true;

    add_action( 'shutdown', function () use ( $context ) {
        flrt_robots_file_sync( $context );
    }, 0 );
}

/* -------------------------------------------------------------------------
 * Triggers
 * ---------------------------------------------------------------------- */

// Plugin settings (the on/off switch lives in wpc_filter_settings), URL
// prefixes, Indexing Depth, permalink mode, site visibility, plugin version.
foreach ( [
    'wpc_filter_settings',
    'wpc_indexing_deep_settings',
    'wpc_filter_permalinks',
    'wpc_seo_rules_settings',
    'permalink_structure',
    'blog_public',
    'flrt_version',
] as $flrt_robots_option ) {
    add_action( 'update_option_' . $flrt_robots_option, function () use ( $flrt_robots_option ) {
        flrt_robots_file_queue( 'option:' . $flrt_robots_option );
    } );
    add_action( 'add_option_' . $flrt_robots_option, function () use ( $flrt_robots_option ) {
        flrt_robots_file_queue( 'option:' . $flrt_robots_option );
    } );
}
unset( $flrt_robots_option );

/**
 * Post types whose changes alter the rules: SEO Rules (what is indexable),
 * filters (URL prefixes / slugs) and Filter Sets (which filters exist).
 *
 * @return string[]
 */
function flrt_robots_file_post_types()
{
    $types = [ FLRT_FILTERS_POST_TYPE, FLRT_FILTERS_SET_POST_TYPE ];
    if ( defined( 'FLRT_SEO_RULES_POST_TYPE' ) ) {
        $types[] = FLRT_SEO_RULES_POST_TYPE;
    }
    return $types;
}

add_action( 'save_post', function ( $post_id, $post ) {
    if ( $post instanceof WP_Post && in_array( $post->post_type, flrt_robots_file_post_types(), true ) ) {
        if ( $post->post_status === 'auto-draft' || wp_is_post_revision( $post_id ) ) {
            return;
        }
        flrt_robots_file_queue( 'save_post:' . $post->post_type );
    }
}, 10, 2 );

add_action( 'deleted_post', function ( $post_id, $post = null ) {
    $type = $post instanceof WP_Post ? $post->post_type : get_post_type( $post_id );
    if ( $type && in_array( $type, flrt_robots_file_post_types(), true ) ) {
        flrt_robots_file_queue( 'deleted_post:' . $type );
    }
}, 10, 2 );

add_action( 'transition_post_status', function ( $new_status, $old_status, $post ) {
    if ( $new_status === $old_status || ! $post instanceof WP_Post ) {
        return;
    }
    if ( in_array( $post->post_type, flrt_robots_file_post_types(), true ) ) {
        flrt_robots_file_queue( 'status:' . $post->post_type );
    }
}, 10, 3 );

// Daily safety net: the file was restored from a backup, rewritten by a
// robots.txt editor, or the rules changed through a path without a hook.
add_action( 'init', function () {
    if ( ! function_exists( 'flrt_robots_helper_available' ) || ! flrt_robots_helper_available() ) {
        return;
    }
    if ( ! wp_next_scheduled( FLRT_ROBOTS_FILE_CRON_HOOK ) ) {
        wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', FLRT_ROBOTS_FILE_CRON_HOOK );
    }
}, 20 );

add_action( FLRT_ROBOTS_FILE_CRON_HOOK, function () {
    flrt_robots_file_sync( 'cron' );
} );

/**
 * Deactivation / uninstall: take the block out and stop the cron. Called from
 * Plugin::deactivate() and Plugin::uninstall() behind function_exists().
 */
function flrt_robots_file_teardown()
{
    wp_clear_scheduled_hook( FLRT_ROBOTS_FILE_CRON_HOOK );

    if ( function_exists( 'flrt_robots_helper_available' ) && flrt_robots_helper_available() ) {
        flrt_robots_file_remove( 'deactivate' );
    }
}

/* -------------------------------------------------------------------------
 * "Retry" / "Write now" button on the settings page
 * ---------------------------------------------------------------------- */

add_action( 'admin_post_' . FLRT_ROBOTS_FILE_ACTION, function () {
    check_admin_referer( FLRT_ROBOTS_FILE_ACTION );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You are not allowed to do this.', 'filter-everything' ) );
    }

    $ok = flrt_robots_file_sync( 'manual' );

    $redirect = remove_query_arg( [ 'wpc-robots-file', 'settings-updated' ], wp_get_referer() ?: admin_url( 'edit.php?post_type=' . FLRT_FILTERS_SET_POST_TYPE . '&page=filters-settings' ) );
    $redirect = add_query_arg( 'wpc-robots-file', $ok ? 'ok' : 'failed', $redirect );

    wp_safe_redirect( $redirect );
    exit;
} );

/**
 * Markup for the status line under the rules textarea (settings page).
 * Used by SettingsTab::renderRobotsRules() behind function_exists().
 *
 * @return string HTML
 */
function flrt_robots_file_status_html()
{
    $check = flrt_robots_file_check();
    $state = flrt_robots_file_state();
    $html  = '';

    // A nonced link, not a <form>: this markup is printed inside the settings
    // page's own options.php form, and a nested <form> is dropped by the HTML
    // parser — its action/nonce fields then went to options.php instead.
    $button = sprintf(
        '<a href="%s" class="button button-secondary wpc-robots-file-retry" style="margin-left:8px">%s</a>',
        esc_url( wp_nonce_url( add_query_arg( 'action', FLRT_ROBOTS_FILE_ACTION, admin_url( 'admin-post.php' ) ), FLRT_ROBOTS_FILE_ACTION ) ),
        esc_html__( 'Retry', 'filter-everything' )
    );

    $file_link = '<a href="' . esc_url( home_url( '/robots.txt' ) ) . '" target="_blank" rel="noopener">robots.txt</a>';

    switch ( $check['code'] ) {
        case 'synced':
            $when = $state['time']
                ? sprintf(
                    /* translators: %s: date and time of the last write */
                    esc_html__( 'Last written %s.', 'filter-everything' ),
                    esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $state['time'] ) )
                )
                : '';
            $html .= sprintf(
                '<p class="description">%s %s</p>',
                sprintf(
                    /* translators: %s: link to the site's robots.txt */
                    esc_html__( 'These rules are written into your physical %s file automatically, between the "# BEGIN Filter Everything" and "# END Filter Everything" markers, and kept up to date whenever your filters, URL prefixes, Indexing Depth or SEO Rules change. Everything else in the file is left untouched.', 'filter-everything' ),
                    $file_link
                ),
                $when
            );
            break;

        case 'stale':
            $html .= sprintf(
                '<p class="wpc-warning">%s%s</p>',
                esc_html__( 'The block in your robots.txt file is out of date (the file was probably edited or restored). Click Retry to write the current rules.', 'filter-everything' ),
                $button
            );
            if ( $state['status'] === 'error' && $state['message'] ) {
                $html .= '<p class="description">' . wp_kses( $state['message'], [ 'code' => [] ] ) . '</p>';
            }
            break;

        case 'blocked':
            foreach ( $check['blockers'] as $code => $message ) {
                $html .= '<p class="wpc-warning">' . wp_kses( $message, [ 'code' => [] ] ) . ( $code === 'not_writable' ? $button : '' ) . '</p>';
            }
            break;

        case 'disabled':
            $html .= sprintf(
                '<p class="description">%s</p>',
                esc_html__( 'Your site has a physical robots.txt file. Enable the option above and the plugin writes these rules into it automatically — or copy them into the file yourself.', 'filter-everything' )
            );
            break;

        case 'virtual':
        default:
            // No physical file: the caller shows the virtual-robots.txt messages.
            return '';
    }

    return $html;
}
