<?php

namespace FilterEverything\Filter\Pro;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WriteXml')):
    class WriteXml
    {
        private $limit = 1000;

        private $filesNum = 1;

        public $file = '';

        private $files = [];

        private $date;

        public $directory = '';

        private $sitemap_style_path;

        private $sitemapFileName = 'filter-sitemap';

        private $indexSitemapFileName = 'filter-sitemap-index';

        public $error;

        public function __construct()
        {

            $this->error = new \WP_Error();

            $this->date = date('Y-m-d');

            $this->sitemap_style_path = FLRT_PLUGIN_DIR_URL . '/assets/css/sitemap.xsl';

            $this->directory = FLRT_XML_PATH;

        }

        public function createXmlFiles($links)
        {
            wpc_clear_folder($this->directory);
            $limit = $this->limit;
            $site_url = home_url();
            $xml = '';
            foreach ($links as $link => $val) {
                if ($limit == $this->limit) {
                    $xml .= $this->sitemapTemplateOpenTag();
                }
                $xml .= sprintf($this->sitemapTemplateUrl(), $site_url . htmlspecialchars($link, ENT_QUOTES | ENT_XML1, 'UTF-8'));
                unset($links[$link]);
                $limit--;
                if ($limit == 0 || empty($links)) {
                    $xml .= $this->sitemapTemplateCloseTag();
                    $limit = $this->limit;
                    $file_name = $this->sitemapFileName . $this->filesNum;
                    $this->files[] = fltr_get_url_from_absolute_path($this->saveXmlToFile($xml, $file_name));
                    $this->filesNum++;
                    $xml = '';
                    if (empty($links)) {
                        $xml .= $this->indexSitemapTemplateOpenTag();
                        foreach ($this->files as $file_link) {
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

        private function xmlUpdateDate()
        {
            $timezone = wp_timezone();

            if ($timezone instanceof DateTimeZone) {
                date_default_timezone_set($timezone->getName());
            } else {
                date_default_timezone_set('UTC');
            }

            $now = new \DateTimeImmutable('now', $timezone);
            $date = $now->format('Y-m-d H:i:s');

            $wpc_xml_write_date = get_option('wpc_xml_write_date');

            if (!$wpc_xml_write_date) {
                add_option('wpc_xml_write_date', $date);
            } else {
                update_option('wpc_xml_write_date', $date);
            }
        }

        private function sitemapTemplateOpenTag()
        {
            return '<?xml version="1.0" encoding="UTF-8"?>
                <?xml-stylesheet type="text/xsl" href="' . $this->sitemap_style_path . '"?>
                <urlset xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd" xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        }

        private function sitemapTemplateCloseTag()
        {
            return '</urlset>';
        }

        private function sitemapTemplateUrl()
        {
            return '<url><loc>%s</loc></url>';
        }

        private function indexSitemapTemplateOpenTag()
        {
            return '<?xml version="1.0" encoding="UTF-8"?>
                <?xml-stylesheet type="text/xsl" href="' . $this->sitemap_style_path . '"?>
                <sitemapindex xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/siteindex.xsd" xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        }

        private function indexSitemapTemplateUrl()
        {
            return '<sitemap><loc>%s</loc><lastmod>%s</lastmod></sitemap>';
        }

        private function indexSitemapTemplateCloseTag()
        {
            return '</sitemapindex>';
        }

        private function saveXmlToFile(string $xmlContent, string $fileName)
        {

            if (!is_dir($this->directory)) {
                if (!mkdir($this->directory, 0755, true)) {
                    $this->error->add('create_folder_error', esc_html__("Error: Unable to create a folder for the XML sitemap file. Please check your server's write permissions or try again later.", 'filter-everything'));
                }
            }

            if (pathinfo($fileName, PATHINFO_EXTENSION) !== 'xml') {
                $fileName .= '.xml';
            }

            $filePath = rtrim($this->directory, '/\\') . DIRECTORY_SEPARATOR . $fileName;

            if (file_exists($filePath)) {
                if (!unlink($filePath)) {
                    $this->error->add('delete_file_error', esc_html__('Error: Unable to delete the existing XML sitemap file. Please check file permissions and try generating it again.', 'filter-everything'));
                }
            }

            if (file_put_contents($filePath, $xmlContent) === false) {
                $this->error->add('write_file_error', esc_html__('Error: Unable to write data to the XML sitemap file. Please verify write permissions and available disk space.', 'filter-everything'));
            }

            return $this->directory . $fileName;
        }
    }
endif;