<?php


namespace FilterEverything\Filter\Pro\Settings\Tabs;

if ( ! defined('ABSPATH') ) {
    exit;
}

use FilterEverything\Filter\BaseSettings;
use FilterEverything\Filter\Container;
use FilterEverything\Filter\SeoTabTrait;

class IndexingDepth extends BaseSettings
{
    use SeoTabTrait;
    private $em;

    private $fse;

    protected $page     = 'wpc-filter-admin-permalinks';

    protected $group = 'wpc_indexing_deep';

    public $optionName = 'wpc_indexing_deep_settings';

    public function __construct()
    {
        $this->init();
    }

    public function init()
    {
        add_action( 'admin_init', array( $this, 'initSettings'), 11 );

        $this->em   = Container::instance()->getEntityManager();
        $this->fse  = Container::instance()->getFilterSetService();
    }

    public function initSettings()
    {
        $settings = [];

        $postTypes = $this->fse->getPostTypes();

        foreach( $postTypes as $postType => $postLabel ){

            if( ! $this->em->hasPostTypeFilters( $postType ) ){
                continue;
            }

            $settings['wpc_indexing_deep_' . $postType] = array(
                'label'  => wp_kses(
                    sprintf(
                        __('%s ( <span class="wpc-settings-post-type-label">%s</span> )', 'filter-everything' ),
                        $postLabel,
                        $postType),
                    array('span' => array( 'class' => true ) )
                ),
                'fields' => array(
                    $postType.'_index_deep' => array(
                        'type'  => 'number',
                        'id' => $postType.'_index_deep'
                    )
                )
            );

        }
        $is_settings_empty = false;
        if( empty( $settings ) ){
            $settings['wpc_indexing_deep_empty_settings'] = array(
                'label' => '',
            );
            $is_settings_empty = true;
        }

        if (!empty($settings)) {
            $first_key = array_key_first($settings);
            $settings = $this->addSectionSettingsWrapper($settings);
            add_action('wpc_before_seo_setting_section_title_' . $first_key, array( $this, 'indexingDepthExplanationMessage' ));
            if( $is_settings_empty ){
                add_action('wpc_before_seo_setting_section_title_' . $first_key, array( $this, 'noPostTypesFiltersMessage' ));
            }
        }

        register_setting($this->group, $this->optionName);

        /**
         * @see https://developer.wordpress.org/reference/functions/add_settings_field/
         */

        $this->registerSettings($settings, $this->page, $this->optionName);
    }

    public function indexingDepthExplanationMessage( $page )
    {
        if( $page === $this->page ) {
            echo '<p class="wpc-setting-description">';
            echo wp_kses(
                __('By default, all filtering results pages are closed from indexing.<br />These settings determine a maximum number of filters (only filters, not archive page)<br /> will be indexed by Search Engines.', 'filter-everything'),
                array( 'br' => array() )
            );
            echo flrt_tooltip( array(
                    'tooltip' => wp_kses(
                        __('For example, for Post Type Products Indexing depth is 2. It means the page with URL path:<br />/color-blue/size-large/<br />will be indexed.<br />But the page with URL path:<br />/color-blue/size-large/shape-round/<br />will NOT be indexed because it contains more than 2 filters.', 'filter-everything'),
                        array('br' => array() )
                    )
                )
            );
            echo '</p>';
        }
    }

    public function noPostTypesFiltersMessage($page)
    {
        if( $page === $this->page ) {
            echo '<p>' . esc_html__('There are no Post types to filter. Create a Filter Set first.', 'filter-everything') . '</p>';
        }
    }

    public function getLabel()
    {
        return esc_html__('Indexing Depth', 'filter-everything');
    }

    public function sectionName()
    {
        return esc_html__('Indexing Depth', 'filter-everything');
    }

    public function getName()
    {
        return 'indexingepth';
    }

    public function valid()
    {
        return true;
    }
}