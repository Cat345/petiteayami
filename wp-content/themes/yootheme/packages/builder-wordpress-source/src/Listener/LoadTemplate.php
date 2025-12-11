<?php

namespace YOOtheme\Builder\Wordpress\Source\Listener;

use YOOtheme\Builder;
use YOOtheme\Builder\Templates\TemplateHelper;
use YOOtheme\Config;
use YOOtheme\Event;
use YOOtheme\View;
use function YOOtheme\app;

class LoadTemplate
{
    public const MAX_POSTS_PER_PAGE = 100;

    public Config $config;

    /**
     * @var ?array
     */
    protected $currentView;

    /**
     * @var ?array
     */
    protected $templateFromRequest;

    /**
     * @var ?array
     */
    protected $matchedTemplate;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Match template.
     *
     * @param \WP_Query $query
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
    }

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

    protected function matchTemplate($query = null)
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

    protected function getTemplate(): ?array
    {
        return $this->templateFromRequest ?? $this->matchedTemplate;
    }
}
