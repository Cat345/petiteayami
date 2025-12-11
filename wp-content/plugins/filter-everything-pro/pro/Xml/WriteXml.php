<?php

namespace FilterEverything\Filter\Pro;

if ( ! defined('ABSPATH') ) {
    exit;
}

class WriteXml
{
    private $limit = 1000;

    private $filesNum = 1;

    public $file = '';

    private $files = [];

    private $date;

    private $directory = '';

    private $sitemap_style_path;

    private $sitemapFileName = 'filter-sitemap';

    private $indexSitemapFileName = 'filter-sitemap-index';

    public function __construct($links){

        $this->date = date('Y-m-d');

        $this->sitemap_style_path = FLRT_PLUGIN_DIR_URL . '/assets/css/sitemap.xsl';

        $this->directory = FLRT_XML_PATH;

        $this->createXmlFiles($links);
    }

    private function createXmlFiles($links){
        $this->clearFolder($this->directory);
        $limit = $this->limit;
        $site_url = home_url();
        $xml = '';
        foreach ($links as $link => $val){
            if($limit == $this->limit){
                $xml .= $this->sitemapTemplateOpenTag();
            }
            $xml .= sprintf($this->sitemapTemplateUrl(), $site_url . htmlspecialchars( $link, ENT_QUOTES | ENT_XML1, 'UTF-8'));
            unset($links[$link]);
            $limit--;
            if($limit == 0 || empty($links)){
                $xml .= $this->sitemapTemplateCloseTag();
                $limit = $this->limit;
                $file_name = $this->sitemapFileName . $this->filesNum;
                $this->files[] = fltr_get_url_from_absolute_path($this->saveXmlToFile($xml, $file_name));
                $this->filesNum++;
                $xml = '';
                if(empty($links)){
                    $xml .= $this->indexSitemapTemplateOpenTag();
                    foreach ($this->files as $file_link){
                        $xml .= sprintf($this->indexSitemapTemplateUrl(), $file_link, $this->date);
                    }
                    $xml .= $this->indexSitemapTemplateCloseTag();
                    $this->file = fltr_get_url_from_absolute_path($this->saveXmlToFile($xml, $this->indexSitemapFileName));
                    $xml = '';
                }
            }
        }
        $this->xmlUpdateDate();
    }

    private function xmlUpdateDate(){
        date_default_timezone_set(get_option('timezone_string'));
        $date = date("Y-m-d H:i:s");
        $wpc_xml_write_date = get_option('wpc_xml_write_date');

        if(!$wpc_xml_write_date){
            add_option('wpc_xml_write_date', $date, 'no');
        }else{
            update_option('wpc_xml_write_date', $date);
        }
    }

    private function sitemapTemplateOpenTag(){
        return '<?xml version="1.0" encoding="UTF-8"?>
                <?xml-stylesheet type="text/xsl" href="' . $this->sitemap_style_path . '"?>
                <urlset xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd" xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    }

    private function sitemapTemplateCloseTag(){
        return '</urlset>';
    }

    private function sitemapTemplateUrl(){
        return '<url><loc>%s</loc></url>';
    }

    private function indexSitemapTemplateOpenTag(){
        return '<?xml version="1.0" encoding="UTF-8"?>
                <?xml-stylesheet type="text/xsl" href="' . $this->sitemap_style_path . '"?>
                <sitemapindex xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/siteindex.xsd" xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    }

    private function indexSitemapTemplateUrl(){
        return '<sitemap><loc>%s</loc><lastmod>%s</lastmod></sitemap>';
    }

    private function indexSitemapTemplateCloseTag(){
        return '</sitemapindex>';
    }

    private function saveXmlToFile(string $xmlContent, string $fileName) {

        if (!is_dir($this->directory)) {
            if (!mkdir($this->directory, 0755, true)) {
                throw new Exception(esc_html__('Failed to create folder:', 'filter-everything') . " $this->directory");
            }
        }

        if (pathinfo($fileName, PATHINFO_EXTENSION) !== 'xml') {
            $fileName .= '.xml';
        }

        $filePath = rtrim($this->directory, '/\\') . DIRECTORY_SEPARATOR . $fileName;

        if (file_exists($filePath)) {
            if (!unlink($filePath)) {
                throw new Exception(esc_html__('Unable to delete existing file:', 'filter-everything') . "$filePath");
            }
        }

        if (file_put_contents($filePath, $xmlContent) === false) {
            throw new Exception(esc_html__('Failed to write file:', 'filter-everything') . "$filePath");
        }

        return $this->directory . $fileName;
    }

    private function clearFolder($directory) {

        $files = glob(rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . '*');

        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}