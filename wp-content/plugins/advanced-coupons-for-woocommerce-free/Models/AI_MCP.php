<?php
namespace ACFWF\Models;

use ACFWF\Abstracts\Abstract_Main_Plugin_Class;
use ACFWF\Abstracts\Base_Model;
use ACFWF\Helpers\Helper_Functions;
use ACFWF\Helpers\Plugin_Constants;
use ACFWF\Interfaces\Model_Interface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Model that adds the AI / MCP settings section.
 *
 * The section promotes StoreAgent, which serves the Advanced Coupons abilities over MCP, and lets the
 * store owner allow or block every Advanced Coupons write ability in StoreAgent's MCP allowlist at once.
 *
 * Every StoreAgent symbol used here is internal to StoreAgent (there is no public API for MCP settings),
 * so each call is guarded and the section shows the Update StoreAgent state when one is missing.
 *
 * @since 4.7.7
 */
class AI_MCP extends Base_Model implements Model_Interface {
    /*
    |--------------------------------------------------------------------------
    | Constants
    |--------------------------------------------------------------------------
     */

    /**
     * Ability name prefix shared by Advanced Coupons Free, Premium, Gift Cards and Loyalty Program.
     *
     * @since 4.7.7
     * @var string
     */
    const ABILITY_PREFIX = 'advanced-coupons/';

    /**
     * StoreAgent plugin slug on WordPress.org.
     *
     * @since 4.7.7
     * @var string
     */
    const STOREAGENT_SLUG = 'storeagent-ai-for-woocommerce';

    /**
     * Fallback for StoreAgent's MCP allowlist size limit (StoreAgent 1.1.9 `MCP::MAX_ALLOWLIST_ENTRIES`).
     *
     * @since 4.7.7
     * @var int
     */
    const DEFAULT_ALLOWLIST_LIMIT = 200;

    /**
     * StoreAgent state: plugin files are not present.
     *
     * @since 4.7.7
     * @var string
     */
    const STATE_NOT_INSTALLED = 'not_installed';

    /**
     * StoreAgent state: installed but not active.
     *
     * @since 4.7.7
     * @var string
     */
    const STATE_INACTIVE = 'inactive';

    /**
     * StoreAgent state: active, but older than the first release with MCP (1.1.9).
     *
     * @since 4.7.7
     * @var string
     */
    const STATE_OUTDATED = 'outdated';

    /**
     * StoreAgent state: active with MCP support, but not connected or MCP is off.
     *
     * @since 4.7.7
     * @var string
     */
    const STATE_SETUP = 'setup';

    /**
     * StoreAgent state: MCP is serving this store.
     *
     * @since 4.7.7
     * @var string
     */
    const STATE_READY = 'ready';

    /*
    |--------------------------------------------------------------------------
    | Class Properties
    |--------------------------------------------------------------------------
     */

    /**
     * Memoised result of {@see self::_has_mcp_api()}. Null until first evaluated.
     *
     * @since 4.7.7
     * @access private
     * @var bool|null
     */
    private $_has_mcp_api = null;

    /*
    |--------------------------------------------------------------------------
    | Class Methods
    |--------------------------------------------------------------------------
     */

    /**
     * Class constructor.
     *
     * @since 4.7.7
     * @access public
     *
     * @param Abstract_Main_Plugin_Class $main_plugin      Main plugin object.
     * @param Plugin_Constants           $constants        Plugin constants object.
     * @param Helper_Functions           $helper_functions Helper functions object.
     */
    public function __construct( Abstract_Main_Plugin_Class $main_plugin, Plugin_Constants $constants, Helper_Functions $helper_functions ) {
        parent::__construct( $main_plugin, $constants, $helper_functions );
        $main_plugin->add_to_all_plugin_models( $this );
        $main_plugin->add_to_public_models( $this );
    }

    /*
    |--------------------------------------------------------------------------
    | Settings section
    |--------------------------------------------------------------------------
     */

    /**
     * Get the AI / MCP section fields.
     *
     * The panel field has no settings value. The section is not in ACFW_Settings::get_sections(), so the
     * field id never becomes a valid option key for the settings update route.
     *
     * @since 4.7.7
     * @access public
     *
     * @param array $fields Section fields from other callbacks.
     * @return array Section fields.
     */
    public function get_section_fields( $fields = array() ) {
        return array_merge(
            $fields,
            array(
                array(
                    'title' => __( 'AI / MCP', 'advanced-coupons-for-woocommerce-free' ),
                    'type'  => 'title',
                    'desc'  => '',
                    'id'    => 'acfw_ai_mcp_main_title',
                ),
                array(
                    'title' => '',
                    'type'  => 'acfw_ai_mcp',
                    'id'    => 'acfw_ai_mcp_panel',
                    'data'  => $this->_get_panel_data(),
                ),
            )
        );
    }

    /**
     * Build the data the AI / MCP panel renders.
     *
     * @since 4.7.7
     * @access private
     *
     * @return array Panel data.
     */
    private function _get_panel_data() {
        $state          = $this->_get_storeagent_state();
        $can_activate   = current_user_can( 'install_plugins' ) && current_user_can( 'activate_plugins' );
        $can_install    = $can_activate && wp_is_file_mod_allowed( 'acfw_install_plugin' );
        $write_names    = $this->_get_write_abilities();
        $allowed_writes = array_intersect( $write_names, $this->_get_allowlist() );
        $has_endpoint   = in_array( $state, array( self::STATE_SETUP, self::STATE_READY ), true );
        $is_connected   = $has_endpoint && \SAAI\Helpers\Connect::is_connected();
        $version        = defined( 'SAAI_VERSION' ) ? SAAI_VERSION : '';

        switch ( $state ) {
            case self::STATE_NOT_INSTALLED:
                $cta = $can_install
                    ? array(
                        'action' => 'install',
                        'label'  => __( 'Install StoreAgent Free', 'advanced-coupons-for-woocommerce-free' ),
                        'note'   => __( 'Free on WordPress.org. Takes about a minute to set up.', 'advanced-coupons-for-woocommerce-free' ),
                    )
                    : array(
                        'action' => 'link',
                        'label'  => __( 'Get StoreAgent on WordPress.org', 'advanced-coupons-for-woocommerce-free' ),
                        'url'    => $this->_get_wporg_url(),
                        // The site can block installs for everyone (DISALLOW_FILE_MODS), or only this user can lack the capability.
                        'note'   => wp_is_file_mod_allowed( 'acfw_install_plugin' )
                            ? __( 'Ask a site administrator to install StoreAgent.', 'advanced-coupons-for-woocommerce-free' )
                            : __( 'Your site does not allow plugin installs from the dashboard.', 'advanced-coupons-for-woocommerce-free' ),
                    );
                break;

            case self::STATE_INACTIVE:
                if ( $can_activate ) {
                    $cta = array(
                        'action' => 'activate',
                        'label'  => __( 'Activate StoreAgent', 'advanced-coupons-for-woocommerce-free' ),
                        'note'   => __( 'StoreAgent is installed but not active.', 'advanced-coupons-for-woocommerce-free' ),
                    );
                } elseif ( current_user_can( 'activate_plugins' ) ) {
                    // DISALLOW_FILE_MODS removes install_plugins, which the in-place AJAX activation needs, but keeps activate_plugins.
                    $cta = array(
                        'action' => 'link',
                        'label'  => __( 'Activate StoreAgent', 'advanced-coupons-for-woocommerce-free' ),
                        'url'    => admin_url( 'plugins.php' ),
                        'note'   => __( 'StoreAgent is installed but not active.', 'advanced-coupons-for-woocommerce-free' ),
                    );
                } else {
                    $cta = array(
                        'action' => 'none',
                        'label'  => '',
                        'note'   => __( 'StoreAgent is installed but not active. Ask a site administrator to activate it.', 'advanced-coupons-for-woocommerce-free' ),
                    );
                }
                break;

            case self::STATE_OUTDATED:
                $cta = array(
                    'action' => 'link',
                    'label'  => __( 'Update StoreAgent', 'advanced-coupons-for-woocommerce-free' ),
                    'url'    => admin_url( 'plugins.php' ),
                    // A 1.1.9+ version that fails the API check has renamed an internal symbol, so make no version claim.
                    'note'   => '' !== $version && version_compare( $version, '1.1.9', '<' )
                        /* translators: %s: StoreAgent version number. */
                        ? sprintf( __( 'StoreAgent %s is active. MCP needs StoreAgent 1.1.9 or later.', 'advanced-coupons-for-woocommerce-free' ), $version )
                        : __( 'This StoreAgent version does not work with the Advanced Coupons AI / MCP settings. Update StoreAgent to the latest version.', 'advanced-coupons-for-woocommerce-free' ),
                );
                break;

            case self::STATE_SETUP:
                $cta = array(
                    'action' => 'link',
                    'label'  => __( 'Set Up StoreAgent MCP', 'advanced-coupons-for-woocommerce-free' ),
                    // The account connection happens on the StoreAgent dashboard, so send unconnected stores there first.
                    'url'    => $is_connected ? admin_url( 'admin.php?page=storeagent-settings&tab=mcp&subtab=general' ) : admin_url( 'admin.php?page=storeagent-dashboard' ),
                    /* translators: %s: StoreAgent version number. */
                    'note'   => sprintf( __( 'StoreAgent %s is active. Connect your StoreAgent account and turn on MCP to finish setup.', 'advanced-coupons-for-woocommerce-free' ), $version ),
                );
                break;

            default:
                $cta = array(
                    'action' => 'link',
                    'label'  => __( 'Manage StoreAgent Connection', 'advanced-coupons-for-woocommerce-free' ),
                    'url'    => admin_url( 'admin.php?page=storeagent-settings&tab=mcp&subtab=connections' ),
                    /* translators: %s: StoreAgent version number. */
                    'note'   => sprintf( __( 'StoreAgent %s is active. AI can only make changes through MCP connections you approve with read and write access.', 'advanced-coupons-for-woocommerce-free' ), $version ),
                );
                break;
        }

        return array(
            'cta'           => $cta,
            'wporg_url'     => $this->_get_wporg_url(),
            'plugin_slug'   => self::STOREAGENT_SLUG,
            'install_nonce' => $can_activate ? wp_create_nonce( 'acfw_install_plugin' ) : '',
            'write_access'  => array(
                'available' => $is_connected && ! empty( $write_names ),
                // No ACFW abilities register on WordPress < 6.9: ACFW checks for the Abilities API when it loads,
                // which is before StoreAgent loads its bundled copy of the API.
                'note'      => $is_connected && empty( $write_names ) && version_compare( get_bloginfo( 'version' ), '6.9', '<' )
                    ? __( 'Advanced Coupons AI abilities need WordPress 6.9 or later.', 'advanced-coupons-for-woocommerce-free' )
                    : '',
                'enabled'   => ! empty( $write_names ) && count( $allowed_writes ) === count( $write_names ),
                'allowed'   => count( $allowed_writes ),
                'total'     => count( $write_names ),
            ),
            'endpoint'      => array(
                'available' => self::STATE_READY === $state,
                'url'       => $has_endpoint
                    ? \SAAI\Helpers\MCP_Gateway_Client::get_endpoint_url()
                    : 'https://api.storeagent.ai/mcp/' . wp_parse_url( home_url(), PHP_URL_HOST ),
                'help'      => self::STATE_READY === $state
                    ? __( 'Paste this into any MCP client to connect it to your store.', 'advanced-coupons-for-woocommerce-free' )
                    : ( $has_endpoint
                        ? __( 'This is your store\'s endpoint. It works in MCP clients once you connect StoreAgent and turn on MCP.', 'advanced-coupons-for-woocommerce-free' )
                        : __( 'Available once StoreAgent 1.1.9 or later is installed and active.', 'advanced-coupons-for-woocommerce-free' ) ),
            ),
            'clients'       => array(
                array(
                    'name'  => 'Claude',
                    'image' => $this->_constants->IMAGES_ROOT_URL . 'ai-clients/claude.svg',
                    'dark'  => false,
                ),
                array(
                    'name'  => 'ChatGPT',
                    'image' => $this->_constants->IMAGES_ROOT_URL . 'ai-clients/openai-chatgpt.svg',
                    'dark'  => false,
                ),
                array(
                    'name'  => 'Cursor',
                    'image' => $this->_constants->IMAGES_ROOT_URL . 'ai-clients/cursor.svg',
                    'dark'  => true,
                ),
            ),
            'recipes'       => $this->_get_recipes(),
            'abilities_url' => $this->_helper_functions->get_utm_url( 'knowledgebase/ai-abilities/', 'acfwf', 'settings', 'aimcpabilitiesdocs' ),
            'labels'        => array(
                'eyebrow'        => __( 'WordPress Abilities API + Advanced Coupons', 'advanced-coupons-for-woocommerce-free' ),
                'heading'        => __( 'Run Your Coupon Marketing With Your Favorite AI', 'advanced-coupons-for-woocommerce-free' ),
                /* translators: <link> and </link> wrap the StoreAgent plugin name, which shows as a link. Keep both tags. */
                'intro'          => __( 'Connect your store to AI assistants like Claude, ChatGPT and Cursor. Ask them to build a BOGO deal, schedule a weekend sale or check who has store credit, in plain English. Connect them with the free <link>StoreAgent</link> plugin.', 'advanced-coupons-for-woocommerce-free' ),
                'write_toggle'   => __( 'Allow AI to make changes', 'advanced-coupons-for-woocommerce-free' ),
                'any_client'     => __( '+ Any MCP Client', 'advanced-coupons-for-woocommerce-free' ),
                'recipes_title'  => __( 'Try It: Campaigns AI Can Run For You', 'advanced-coupons-for-woocommerce-free' ),
                'recipes_desc'   => __( 'Copy a prompt into your AI assistant. Anything that needs a plugin you don\'t have yet is marked.', 'advanced-coupons-for-woocommerce-free' ),
                'abilities_docs' => __( 'Abilities API Docs', 'advanced-coupons-for-woocommerce-free' ),
                'endpoint_title' => __( 'Your Store\'s MCP Endpoint', 'advanced-coupons-for-woocommerce-free' ),
                'copy'           => __( 'Copy', 'advanced-coupons-for-woocommerce-free' ),
                'copied'         => __( 'The MCP endpoint URL is on your clipboard.', 'advanced-coupons-for-woocommerce-free' ),
                'install_done'   => __( 'StoreAgent is active. Reloading the page…', 'advanced-coupons-for-woocommerce-free' ),
                'write_error'    => __( 'The AI write access could not be changed. Please try again.', 'advanced-coupons-for-woocommerce-free' ),
            ),
        );
    }

    /**
     * Get the example prompts, with a lock label for each prompt that needs a plugin that is not active.
     *
     * @since 4.7.7
     * @access private
     *
     * @return array[] Prompt cards.
     */
    private function _get_recipes() {
        $plugins = array(
            'premium' => array( Plugin_Constants::PREMIUM_PLUGIN, __( 'Premium', 'advanced-coupons-for-woocommerce-free' ) ),
            'agc'     => array( Plugin_Constants::GIFT_CARDS_PLUGIN, __( 'Gift Cards', 'advanced-coupons-for-woocommerce-free' ) ),
            'lpfw'    => array( Plugin_Constants::LOYALTY_PLUGIN, __( 'Loyalty', 'advanced-coupons-for-woocommerce-free' ) ),
        );

        $recipes = array(
            array(
                'title'    => __( 'Weekend Flash Sale', 'advanced-coupons-for-woocommerce-free' ),
                'prompt'   => __( 'Create a 20% off coupon for the Summer collection that runs this Saturday and Sunday only, and give me a link I can share on Instagram.', 'advanced-coupons-for-woocommerce-free' ),
                'steps'    => array( 'create-coupon', 'set-coupon-schedule', 'set-coupon-url' ),
                'requires' => array(),
            ),
            array(
                'title'    => __( 'Coupon Health Check', 'advanced-coupons-for-woocommerce-free' ),
                'prompt'   => __( 'Which coupons drove the most discount last month, and which active coupons haven\'t been used at all?', 'advanced-coupons-for-woocommerce-free' ),
                'steps'    => array( 'get-coupons-summary', 'list-coupons' ),
                'requires' => array(),
            ),
            array(
                'title'    => __( 'Hands-Free Promo', 'advanced-coupons-for-woocommerce-free' ),
                'prompt'   => __( 'Make free shipping auto-apply for any order over $100 every Friday until the end of the year.', 'advanced-coupons-for-woocommerce-free' ),
                'steps'    => array( 'create-coupon', 'set-shipping-override', 'set-auto-apply', 'set-recurring-schedule' ),
                'requires' => array( 'premium' ),
            ),
            array(
                'title'    => __( 'Refund As Store Credit', 'advanced-coupons-for-woocommerce-free' ),
                'prompt'   => __( 'Give Jess Taylor $25 store credit for her delayed order and tell me her new balance.', 'advanced-coupons-for-woocommerce-free' ),
                'steps'    => array( 'get-store-credit-ledger', 'adjust-store-credit' ),
                'requires' => array( 'premium' ),
            ),
            array(
                'title'    => __( 'Save Expiring Points', 'advanced-coupons-for-woocommerce-free' ),
                'prompt'   => __( 'Find customers with points expiring in the next 30 days and create a personal 10% coupon for each of them.', 'advanced-coupons-for-woocommerce-free' ),
                'steps'    => array( 'get-expiring-loyalty-points', 'create-coupon', 'set-coupon-schedule' ),
                'requires' => array( 'lpfw' ),
            ),
            array(
                'title'    => __( 'Reward Your VIPs', 'advanced-coupons-for-woocommerce-free' ),
                'prompt'   => __( 'Give my top 20 loyalty customers 500 bonus points and send each a $10 gift card as a thank you.', 'advanced-coupons-for-woocommerce-free' ),
                'steps'    => array( 'list-top-loyalty-customers', 'issue-loyalty-bonus-points', 'issue-gift-card' ),
                'requires' => array( 'lpfw', 'agc' ),
            ),
            array(
                'title'    => __( 'End-Of-Month Report', 'advanced-coupons-for-woocommerce-free' ),
                'prompt'   => __( 'Summarise coupon usage, loyalty ROI and outstanding gift card liability for last month.', 'advanced-coupons-for-woocommerce-free' ),
                'steps'    => array( 'get-coupons-summary', 'get-loyalty-roi-report', 'get-gift-card-liability' ),
                'requires' => array( 'lpfw', 'agc' ),
            ),
            array(
                'title'    => __( 'Customer Service Fix', 'advanced-coupons-for-woocommerce-free' ),
                'prompt'   => __( 'Sam says his gift card email never arrived. Check the card\'s activity and resend it.', 'advanced-coupons-for-woocommerce-free' ),
                'steps'    => array( 'get-gift-card', 'resend-gift-card-email' ),
                'requires' => array( 'agc' ),
            ),
            array(
                'title'    => __( 'Tune Your Earn Rules', 'advanced-coupons-for-woocommerce-free' ),
                'prompt'   => __( 'Double the points customers earn on orders this Black Friday week, then switch it back after.', 'advanced-coupons-for-woocommerce-free' ),
                'steps'    => array( 'list-loyalty-earn-rules', 'update-loyalty-earn-rule', 'toggle-loyalty-earn-rule' ),
                'requires' => array( 'lpfw' ),
            ),
        );

        return array_map(
            function ( $recipe ) use ( $plugins ) {
                $missing = array();

                foreach ( $recipe['requires'] as $key ) {
                    if ( ! $this->_helper_functions->is_plugin_active( $plugins[ $key ][0] ) ) {
                        $missing[] = $plugins[ $key ][1];
                    }
                }

                unset( $recipe['requires'] );

                /* translators: %s: plugin names joined with " + ", e.g. "Loyalty + Gift Cards". */
                $recipe['needs'] = $missing ? sprintf( __( 'Needs %s', 'advanced-coupons-for-woocommerce-free' ), implode( ' + ', $missing ) ) : '';

                return $recipe;
            },
            $recipes
        );
    }

    /*
    |--------------------------------------------------------------------------
    | StoreAgent helpers
    |--------------------------------------------------------------------------
     */

    /**
     * Get the StoreAgent state.
     *
     * @since 4.7.7
     * @access private
     *
     * @return string One of the STATE_* constants.
     */
    private function _get_storeagent_state() {
        if ( ! $this->_helper_functions->is_plugin_active( Plugin_Constants::STOREAGENT_AI_PLUGIN ) ) {
            return $this->_helper_functions->is_plugin_installed( Plugin_Constants::STOREAGENT_AI_PLUGIN ) ? self::STATE_INACTIVE : self::STATE_NOT_INSTALLED;
        }

        if ( ! $this->_has_mcp_api() ) {
            return self::STATE_OUTDATED;
        }

        return \SAAI\Helpers\MCP::is_available() ? self::STATE_READY : self::STATE_SETUP;
    }

    /**
     * Whether the active StoreAgent has every MCP helper this section calls.
     *
     * @since 4.7.7
     * @access private
     *
     * @return bool True if every helper exists, false otherwise.
     */
    private function _has_mcp_api() {
        if ( null !== $this->_has_mcp_api ) {
            return $this->_has_mcp_api;
        }

        $this->_has_mcp_api = class_exists( '\SAAI\Helpers\MCP' )
            && class_exists( '\SAAI\Helpers\Connect' )
            && class_exists( '\SAAI\Helpers\MCP_Gateway_Client' )
            && class_exists( '\SAAI\Classes\MCP\Mcp_Gateway_Sync' )
            && method_exists( '\SAAI\Helpers\MCP', 'get_settings' )
            && method_exists( '\SAAI\Helpers\MCP', 'update_settings' )
            && method_exists( '\SAAI\Helpers\MCP', 'classify_ability' )
            && method_exists( '\SAAI\Helpers\MCP', 'is_available' )
            && method_exists( '\SAAI\Helpers\Connect', 'is_connected' )
            && method_exists( '\SAAI\Helpers\MCP_Gateway_Client', 'get_endpoint_url' )
            && method_exists( '\SAAI\Classes\MCP\Mcp_Gateway_Sync', 'instance' )
            && method_exists( '\SAAI\Classes\MCP\Mcp_Gateway_Sync', 'sync' )
            && defined( '\SAAI\Helpers\MCP::CLASS_WRITE' );

        return $this->_has_mcp_api;
    }

    /**
     * Get the names of every registered Advanced Coupons ability that StoreAgent treats as a write.
     *
     * @since 4.7.7
     * @access private
     *
     * @return string[] Ability names.
     */
    private function _get_write_abilities() {
        if ( ! $this->_has_mcp_api() || ! function_exists( 'wp_get_abilities' ) ) {
            return array();
        }

        $names = array();

        foreach ( wp_get_abilities() as $ability ) {
            $name = $ability->get_name();

            if ( str_starts_with( $name, self::ABILITY_PREFIX ) && \SAAI\Helpers\MCP::CLASS_WRITE === \SAAI\Helpers\MCP::classify_ability( $ability ) ) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * Get StoreAgent's MCP write allowlist.
     *
     * @since 4.7.7
     * @access private
     *
     * @return string[] Allowed ability names.
     */
    private function _get_allowlist() {
        if ( ! $this->_has_mcp_api() ) {
            return array();
        }

        $settings = \SAAI\Helpers\MCP::get_settings();

        return isset( $settings['allowlist'] ) && is_array( $settings['allowlist'] ) ? $settings['allowlist'] : array();
    }

    /**
     * Get StoreAgent's maximum number of MCP allowlist entries.
     *
     * @since 4.7.7
     * @access private
     *
     * @return int Maximum number of entries.
     */
    private function _get_allowlist_limit() {
        return defined( '\SAAI\Helpers\MCP::MAX_ALLOWLIST_ENTRIES' ) ? (int) \SAAI\Helpers\MCP::MAX_ALLOWLIST_ENTRIES : self::DEFAULT_ALLOWLIST_LIMIT;
    }

    /**
     * Get the StoreAgent WordPress.org URL.
     *
     * @since 4.7.7
     * @access private
     *
     * @return string WordPress.org plugin URL.
     */
    private function _get_wporg_url() {
        return 'https://wordpress.org/plugins/' . self::STOREAGENT_SLUG . '/';
    }

    /*
    |--------------------------------------------------------------------------
    | REST API
    |--------------------------------------------------------------------------
     */

    /**
     * Register the write access REST route.
     *
     * @since 4.7.7
     * @access public
     */
    public function register_routes() {
        \register_rest_route(
            Plugin_Constants::REST_API_NAMESPACE,
            '/ai-mcp/write-access',
            array(
                'methods'             => \WP_REST_Server::CREATABLE,
                'permission_callback' => array( $this, 'write_access_permissions_check' ),
                'callback'            => array( $this, 'update_write_access' ),
                'args'                => array(
                    'enabled' => array(
                        'type'     => 'boolean',
                        'required' => true,
                    ),
                ),
            )
        );
    }

    /**
     * Check if the current user can change the MCP write access.
     *
     * Uses the same capability as StoreAgent's own MCP settings route.
     *
     * @since 4.7.7
     * @access public
     *
     * @return true|\WP_Error True if allowed, WP_Error otherwise.
     */
    public function write_access_permissions_check() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return new \WP_Error( 'rest_forbidden_context', __( 'Sorry, you are not allowed to change the AI write access.', 'advanced-coupons-for-woocommerce-free' ), array( 'status' => \rest_authorization_required_code() ) );
        }

        return true;
    }

    /**
     * Allow or block every Advanced Coupons write ability in StoreAgent's MCP allowlist.
     *
     * Saves the allowlist, then syncs the gateway. This is the same sequence as StoreAgent's own settings
     * save route. Other allowlist entries, `enabled` and `read_denylist` are never changed.
     *
     * @since 4.7.7
     * @access public
     *
     * @param \WP_REST_Request $request Full details about the request.
     * @return \WP_REST_Response|\WP_Error Response object on success, WP_Error on failure.
     */
    public function update_write_access( $request ) {
        if ( ! $this->_helper_functions->is_plugin_active( Plugin_Constants::STOREAGENT_AI_PLUGIN ) || ! $this->_has_mcp_api() || ! \SAAI\Helpers\Connect::is_connected() ) {
            return new \WP_Error( 'acfw_storeagent_not_connected', __( 'Connect StoreAgent 1.1.9 or later first.', 'advanced-coupons-for-woocommerce-free' ), array( 'status' => 400 ) );
        }

        $error       = new \WP_Error( 'acfw_ai_mcp_write_access_failed', __( 'The AI write access could not be changed. Please try again.', 'advanced-coupons-for-woocommerce-free' ), array( 'status' => 500 ) );
        $enable      = (bool) $request->get_param( 'enabled' );
        $write_names = $this->_get_write_abilities();
        $allowlist   = $this->_get_allowlist();

        if ( $enable && empty( $write_names ) ) {
            return $error;
        }

        // Disabling removes every Advanced Coupons entry, including ones for plugins that are inactive right
        // now, so reactivating that plugin does not silently re-allow its writes. Read abilities never use
        // the allowlist, so removing by prefix cannot hide one.
        $allowlist = $enable
            ? array_values( array_unique( array_merge( $allowlist, $write_names ) ) )
            : array_values(
                array_filter(
                    $allowlist,
                    function ( $name ) {
                        return ! str_starts_with( $name, self::ABILITY_PREFIX );
                    }
                )
            );

        // StoreAgent silently truncates an oversized allowlist on save, so refuse before writing anything.
        if ( $enable && count( $allowlist ) > $this->_get_allowlist_limit() ) {
            return new \WP_Error( 'acfw_ai_mcp_allowlist_full', __( 'The StoreAgent MCP allowlist is full. Remove some entries in StoreAgent > Settings > MCP, then try again.', 'advanced-coupons-for-woocommerce-free' ), array( 'status' => 400 ) );
        }

        if ( ! \SAAI\Helpers\MCP::update_settings( array( 'allowlist' => $allowlist ) ) ) {
            return $error;
        }

        $sync     = \SAAI\Classes\MCP\Mcp_Gateway_Sync::instance()->sync( \SAAI\Helpers\MCP::get_settings() );
        $response = array(
            'status'  => 'success',
            'enabled' => $enable,
            'allowed' => $enable ? count( $write_names ) : 0,
            'message' => $enable
                ? __( 'AI can now make changes to your coupons through MCP.', 'advanced-coupons-for-woocommerce-free' )
                : __( 'AI can no longer make changes to your coupons through MCP.', 'advanced-coupons-for-woocommerce-free' ),
        );

        // The allowlist is already saved, so this is a warning, not an error: the switch keeps the saved state.
        if ( is_array( $sync ) && isset( $sync['status'] ) && 'failed' === $sync['status'] ) {
            $response['status']  = 'warning';
            $response['message'] = __( 'The setting was saved, but StoreAgent could not sync it to the MCP gateway. Retry from StoreAgent > Settings > MCP.', 'advanced-coupons-for-woocommerce-free' );
        }

        return \rest_ensure_response( $response );
    }

    /*
    |--------------------------------------------------------------------------
    | Fulfill implemented interface contracts
    |--------------------------------------------------------------------------
     */

    /**
     * Execute AI_MCP class.
     *
     * @since 4.7.7
     * @access public
     * @inherit ACFWF\Interfaces\Model_Interface
     */
    public function run() {
        add_filter( 'acfw_setting_ai_mcp_options', array( $this, 'get_section_fields' ) );
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }
}
