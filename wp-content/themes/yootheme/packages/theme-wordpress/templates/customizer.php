<?php

if (!is_user_logged_in()) {
    wp_safe_redirect(wp_login_url());
    exit();
}

if (!(current_user_can('edit_theme_options') || current_user_can('edit_posts'))) {
    wp_die(
        '<h1>' .
            __('You need a higher level of permission.') .
            '</h1>' .
            '<p>' .
            __('Sorry, you are not allowed to edit theme options on this site.') .
            '</p>',
        403,
    );
}

$title = 'Website Builder';
$hook_suffix = 'yootheme-customizer';

set_current_screen($hook_suffix);

if (is_network_admin()) {
    /* translators: Network admin screen title. %s: Network title. */
    $admin_title = sprintf(__('Network Admin: %s'), get_network()->site_name);
} elseif (is_user_admin()) {
    /* translators: User dashboard screen title. %s: Network title. */
    $admin_title = sprintf(__('User Dashboard: %s'), get_network()->site_name);
} else {
    $admin_title = get_bloginfo('name');
}

/* translators: Admin screen title. 1: Admin screen name, 2: Network or site name. */
$admin_title = sprintf(__('%1$s &lsaquo; %2$s &#8212; WordPress'), $title, $admin_title);
$admin_title = apply_filters('admin_title', $admin_title, $title);

wp_enqueue_media();
wp_enqueue_style('common');

_wp_admin_html_begin();
?>
<title><?= esc_html($admin_title) ?></title>

<script type="text/javascript">
    var ajaxurl = <?php echo wp_json_encode(admin_url('admin-ajax.php', 'relative')); ?>;
</script>

<?php
do_action('admin_enqueue_scripts', $hook_suffix);
do_action("admin_print_styles-{$hook_suffix}");
do_action('admin_print_styles');
do_action("admin_print_scripts-{$hook_suffix}");
do_action('admin_print_scripts');
do_action("admin_head-{$hook_suffix}");
do_action('admin_head');
?>
</head>
<body>
    <div class="uk-noconflict">
        <div id="customizer"></div>
    </div>
    <?php
    do_action('admin_footer', $hook_suffix);
    do_action("admin_print_footer_scripts-{$hook_suffix}");
    do_action('admin_print_footer_scripts');
    ?>
</body>
</html>
