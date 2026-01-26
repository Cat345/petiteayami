<?php

namespace YOOtheme\Builder\Wordpress\Source\Listener;

use WP_Query;
use YOOtheme\Builder;
use YOOtheme\Builder\Templates\TemplateHelper;
use YOOtheme\Builder\Wordpress\Source\Helper;
use YOOtheme\Config;
use YOOtheme\Event;
use YOOtheme\View;
use function YOOtheme\app;

class LoadTemplate
{
    public const MAX_POSTS_PER_PAGE = 500;

    public Config $config;

    /**
     * @var ?array<string, mixed>
     */
    protected ?array $currentView = null;

    /**
     * @var ?array<string, mixed>
     */
    protected ?array $templateFromRequest = null;

    /**
     * @var ?array<string, mixed>
     */
    protected ?array $matchedTemplate = null;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Match template.
     *
     * @param WP_Query $query
     */
    public function match($query): void
    {
        if (!$query->is_main_query()) {
            return;
        }

        $this->matchTemplate($query);

        $template = $this->getTemplate();

        if ($posts = (int) ($template['params']['posts_per_page'] ?? 0)) {
            $query->set('posts_per_page', min($posts, static::MAX_POSTS_PER_PAGE));
        }

        if ($ordering = (string) ($template['params']['order_by'] ?? '')) {
            [$order_by, $order] = explode(',', $ordering);

            $query->set('orderby', $order_by);
            $query->set('order', $order);
        }

        if (!($template['params']['include_children'] ?? true)) {
            Helper::filterOnce('parse_tax_query', function ($query) {
                $query->tax_query->queries[0]['include_children'] = false;
            });
        }
    }

    /**
     * @param string $tpl
     *
     * @return string
     */
    public function include($tpl)
    {
        $this->matchTemplate();

        if (!$this->currentView) {
            return $tpl;
        }

        $sections = app(View::class)['sections'];

        if ($sections->exists('builder') && empty($this->templateFromRequest)) {
            return $tpl;
        }

        // set template identifier
        if ($this->config->get('app.isCustomizer')) {
            $this->config->add('customizer.template', [
                'id' => $this->templateFromRequest['id'] ?? null,
                'visible' => $this->matchedTemplate['id'] ?? null,
            ]);
        }

        if ($template = $this->getTemplate()) {
            $sections->set(
                'builder',
                fn() => app(Builder::class)->render(
                    json_encode($template['layout'] ?? []),
                    ($this->currentView['params'] ?? []) +
                        ($template['params'] ?? []) + [
                            'prefix' => "template-{$template['id']}",
                            'template' => $this->currentView['type'],
                        ],
                ),
            );
            $this->config->add('app.template', $template);
        }

        return $tpl;
    }

    protected function matchTemplate(?WP_Query $query = null): void
    {
        if ($this->currentView) {
            return;
        }

        $this->currentView = Event::emit('builder.template', $query ?? $GLOBALS['wp_query']);

        if (!$this->currentView) {
            return;
        }

        if ($this->config->get('app.isCustomizer')) {
            $this->config->set('customizer.view', $this->currentView['type']);
        }

        $this->templateFromRequest = $this->config->get('req.customizer.template');
        $this->matchedTemplate = app(TemplateHelper::class)->match($this->currentView);
    }

    /**
     * @return ?array<string, mixed>
     */
    protected function getTemplate(): ?array
    {
        return $this->templateFromRequest ?? $this->matchedTemplate;
    }
}
