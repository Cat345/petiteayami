<?php

namespace YOOtheme\Builder\Wordpress\Source\Listener;

class LoadTemplateUrl
{
    public static function handle(array $template): array
    {
        static $registered = false;

        if (!$registered) {
            $registered = add_filter(
                'get_archives_link',
                [static::class, 'getArchivesLink'],
                10,
                4,
            );
        }

        $type = $template['type'] ?? '';

        if (str_starts_with($type, 'single-') && ($posts = static::getPosts($template))) {
            $template['url'] = get_permalink($posts[0]);
        } elseif (str_starts_with($type, 'taxonomy-') && ($terms = static::getTerms($template))) {
            $template['url'] = get_term_link($terms[0], substr($type, 9));
        } elseif ($type === 'date-archive' && ($archives = static::getArchives($template))) {
            $template['url'] = html_entity_decode($archives);
        } elseif ($type === 'author-archive') {
            $template['url'] = get_author_posts_url(get_current_user_id());
        } elseif (str_starts_with($type, 'archive-')) {
            $template['url'] = get_post_type_archive_link(substr($type, 8));
        } elseif ($type === 'search') {
            $template['url'] = get_search_link();
        } elseif ($type === '_search') {
            $template['url'] = '#live-search';
        } elseif ($type === 'error-404') {
            $template['url'] = home_url('?p=-1');
        }

        return $template;
    }

    public static function getArchivesLink($link_html, $url, $text, $format): string
    {
        return $format === 'url' ? $url : $link_html;
    }

    protected static function getArchives(array $template): string
    {
        $type = $template['query']['archive'] ?? '';
        $types = [
            'day' => 'daily',
            'month' => 'monthly',
            'year' => 'yearly',
        ];

        return $type !== 'time'
            ? wp_get_archives([
                'type' => $types[$type] ?? '',
                'echo' => false,
                'limit' => 1,
                'format' => 'url',
            ])
            : '';
    }

    protected static function getTerms(array $template): array
    {
        $args = [
            'number' => 1,
            'fields' => 'ids',
            'taxonomy' => substr($template['type'], 9),
        ];

        $templateTerms = $template['query']['terms'] ?? [];
        $includeChildren = $template['query']['include_children'] ?? false;

        if ($templateTerms && $includeChildren === 'only') {
            $terms = [];
            foreach ($templateTerms as $termId) {
                $args += ['child_of' => $termId];
                $terms = get_terms($args);
                if (!empty($terms)) {
                    break;
                }
            }
        } else {
            $args += ['include' => $templateTerms];
            $terms = get_terms($args);
        }

        return is_array($terms) ? $terms : [];
    }

    protected static function getPosts(array $template): array
    {
        $args = [
            'post_type' => substr($template['type'], 7),
            'limit' => 1,
        ];

        if ($posts_page = get_option('page_for_posts')) {
            $args['exclude'] = [$posts_page];
        }

        if ($terms = $template['query']['terms'] ?? []) {
            $args['terms'] = $terms;

            foreach ($template['query'] as $key => $value) {
                if (str_ends_with($key, '_include_children')) {
                    $args[$key] = $value;
                }
            }
        }

        return get_posts($args);
    }
}
