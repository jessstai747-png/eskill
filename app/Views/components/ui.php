<?php
/**
 * UI Components Library
 * Componentes reutilizáveis para o dashboard
 *
 * Uso: <?php \App\Views\Components\UI::statCard(...) ?>
 */

namespace App\Views\Components;

class UI
{
    /**
     * Card de estatística com ícone
     */
    public static function statCard(
        string $title,
        string $value,
        string $icon,
        string $color = 'primary',
        ?string $change = null,
        ?string $changeType = null,
        ?string $subtitle = null
    ): string {
        $changeHtml = '';
        if ($change !== null) {
            $changeIcon = $changeType === 'up' ? 'bi-arrow-up' : ($changeType === 'down' ? 'bi-arrow-down' : 'bi-dash');
            $changeColor = $changeType === 'up' ? 'success' : ($changeType === 'down' ? 'danger' : 'secondary');
            $changeHtml = "
                <div class='stat-change text-{$changeColor}'>
                    <i class='bi {$changeIcon}'></i>
                    <span>{$change}</span>
                </div>
            ";
        }

        $subtitleHtml = $subtitle ? "<div class='stat-subtitle text-muted'>{$subtitle}</div>" : '';

        return <<<HTML
        <div class="stat-card">
            <div class="stat-icon bg-{$color} bg-opacity-10 text-{$color}">
                <i class="bi {$icon}"></i>
            </div>
            <div class="stat-content">
                <div class="stat-title">{$title}</div>
                <div class="stat-value">{$value}</div>
                {$subtitleHtml}
            </div>
            {$changeHtml}
        </div>
        HTML;
    }

    /**
     * Card de ação rápida
     */
    public static function actionCard(
        string $title,
        string $description,
        string $icon,
        string $color = 'primary',
        string $href = '#',
        ?string $badge = null
    ): string {
        $badgeHtml = $badge ? "<span class='action-badge bg-{$color}'>{$badge}</span>" : '';

        return <<<HTML
        <a href="{$href}" class="action-card" data-color="{$color}">
            <div class="action-icon bg-{$color} bg-opacity-10 text-{$color}">
                <i class="bi {$icon}"></i>
            </div>
            <div class="action-content">
                <div class="action-title">{$title}{$badgeHtml}</div>
                <div class="action-description">{$description}</div>
            </div>
            <i class="bi bi-chevron-right action-arrow"></i>
        </a>
        HTML;
    }

    /**
     * Empty state ilustrado
     */
    public static function emptyState(
        string $title,
        string $description,
        string $icon = 'bi-inbox',
        ?string $actionText = null,
        ?string $actionHref = null
    ): string {
        $actionHtml = '';
        if ($actionText && $actionHref) {
            $actionHtml = "<a href='{$actionHref}' class='btn btn-primary mt-3'>{$actionText}</a>";
        }

        return <<<HTML
        <div class="empty-state">
            <div class="empty-icon">
                <i class="bi {$icon}"></i>
            </div>
            <h5 class="empty-title">{$title}</h5>
            <p class="empty-description">{$description}</p>
            {$actionHtml}
        </div>
        HTML;
    }

    /**
     * Loading skeleton
     */
    public static function skeleton(string $type = 'text', int $count = 1): string
    {
        $html = '';
        for ($i = 0; $i < $count; $i++) {
            switch ($type) {
                case 'card':
                    $html .= '<div class="skeleton-card"><div class="skeleton skeleton-title"></div><div class="skeleton skeleton-text"></div><div class="skeleton skeleton-text short"></div></div>';
                    break;
                case 'avatar':
                    $html .= '<div class="skeleton skeleton-avatar"></div>';
                    break;
                case 'button':
                    $html .= '<div class="skeleton skeleton-button"></div>';
                    break;
                default:
                    $html .= '<div class="skeleton skeleton-text"></div>';
            }
        }
        return $html;
    }

    /**
     * Progress bar com label
     */
    public static function progressBar(
        int $value,
        string $label = '',
        string $color = 'primary',
        bool $striped = false,
        bool $animated = false
    ): string {
        $classes = "progress-bar bg-{$color}";
        if ($striped) $classes .= ' progress-bar-striped';
        if ($animated) $classes .= ' progress-bar-animated';

        $labelHtml = $label ? "<div class='progress-label'><span>{$label}</span><span>{$value}%</span></div>" : '';

        return <<<HTML
        <div class="progress-wrapper">
            {$labelHtml}
            <div class="progress">
                <div class="{$classes}" role="progressbar" style="width: {$value}%" aria-valuenow="{$value}" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
        </div>
        HTML;
    }

    /**
     * Badge com variantes
     */
    public static function badge(string $text, string $variant = 'primary', bool $pill = true, ?string $icon = null): string
    {
        $pillClass = $pill ? 'rounded-pill' : '';
        $iconHtml = $icon ? "<i class='bi {$icon} me-1'></i>" : '';

        return "<span class='badge bg-{$variant} {$pillClass}'>{$iconHtml}{$text}</span>";
    }

    /**
     * Alert com ícone
     */
    public static function alert(string $message, string $type = 'info', bool $dismissible = true, ?string $title = null): string
    {
        $icons = [
            'success' => 'bi-check-circle-fill',
            'danger' => 'bi-exclamation-triangle-fill',
            'warning' => 'bi-exclamation-circle-fill',
            'info' => 'bi-info-circle-fill'
        ];
        $icon = $icons[$type] ?? $icons['info'];

        $dismissBtn = $dismissible ? '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' : '';
        $titleHtml = $title ? "<h6 class='alert-heading mb-1'>{$title}</h6>" : '';

        return <<<HTML
        <div class="alert alert-{$type} d-flex align-items-start" role="alert">
            <i class="bi {$icon} me-3 mt-1"></i>
            <div class="flex-grow-1">
                {$titleHtml}
                <p class="mb-0">{$message}</p>
            </div>
            {$dismissBtn}
        </div>
        HTML;
    }

    /**
     * Avatar com fallback
     */
    public static function avatar(
        ?string $src,
        string $name = 'U',
        string $size = 'md',
        ?string $status = null
    ): string {
        $sizes = ['sm' => '32px', 'md' => '40px', 'lg' => '56px', 'xl' => '80px'];
        $sizeVal = $sizes[$size] ?? $sizes['md'];

        $statusHtml = '';
        if ($status) {
            $statusHtml = "<span class='avatar-status bg-{$status}'></span>";
        }

        if ($src) {
            $content = "<img src='{$src}' alt='{$name}' class='avatar-img'>";
        } else {
            $initial = strtoupper(substr($name, 0, 1));
            $content = "<span class='avatar-initial'>{$initial}</span>";
        }

        return <<<HTML
        <div class="avatar" style="width: {$sizeVal}; height: {$sizeVal};">
            {$content}
            {$statusHtml}
        </div>
        HTML;
    }

    /**
     * Tooltip wrapper
     */
    public static function tooltip(string $content, string $text, string $placement = 'top'): string
    {
        return "<span data-bs-toggle='tooltip' data-bs-placement='{$placement}' title='{$text}'>{$content}</span>";
    }

    /**
     * Dropdown menu
     */
    public static function dropdown(string $trigger, array $items, string $align = 'end'): string
    {
        $itemsHtml = '';
        foreach ($items as $item) {
            if ($item === 'divider') {
                $itemsHtml .= '<li><hr class="dropdown-divider"></li>';
            } else {
                $icon = isset($item['icon']) ? "<i class='bi {$item['icon']} me-2'></i>" : '';
                $class = $item['class'] ?? '';
                $itemsHtml .= "<li><a class='dropdown-item {$class}' href='{$item['href']}'>{$icon}{$item['label']}</a></li>";
            }
        }

        return <<<HTML
        <div class="dropdown">
            <button class="btn btn-link dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                {$trigger}
            </button>
            <ul class="dropdown-menu dropdown-menu-{$align}">
                {$itemsHtml}
            </ul>
        </div>
        HTML;
    }

    /**
     * Tabs navigation
     */
    public static function tabs(array $tabs, string $activeId): string
    {
        $navItems = '';
        $tabContent = '';

        foreach ($tabs as $id => $tab) {
            $active = $id === $activeId;
            $activeClass = $active ? 'active' : '';
            $selected = $active ? 'true' : 'false';
            $show = $active ? 'show active' : '';

            $navItems .= <<<HTML
            <li class="nav-item" role="presentation">
                <button class="nav-link {$activeClass}" id="{$id}-tab" data-bs-toggle="tab" data-bs-target="#{$id}" type="button" role="tab" aria-controls="{$id}" aria-selected="{$selected}">
                    {$tab['label']}
                </button>
            </li>
            HTML;

            $tabContent .= <<<HTML
            <div class="tab-pane fade {$show}" id="{$id}" role="tabpanel" aria-labelledby="{$id}-tab">
                {$tab['content']}
            </div>
            HTML;
        }

        return <<<HTML
        <ul class="nav nav-tabs" role="tablist">
            {$navItems}
        </ul>
        <div class="tab-content mt-3">
            {$tabContent}
        </div>
        HTML;
    }

    /**
     * Timeline item
     */
    public static function timelineItem(
        string $title,
        string $description,
        string $time,
        string $icon = 'bi-circle-fill',
        string $color = 'primary'
    ): string {
        return <<<HTML
        <div class="timeline-item">
            <div class="timeline-marker bg-{$color}">
                <i class="bi {$icon}"></i>
            </div>
            <div class="timeline-content">
                <div class="timeline-header">
                    <span class="timeline-title">{$title}</span>
                    <span class="timeline-time">{$time}</span>
                </div>
                <p class="timeline-description">{$description}</p>
            </div>
        </div>
        HTML;
    }

    /**
     * Data table wrapper
     */
    public static function dataTable(array $columns, array $rows, ?string $emptyMessage = null): string
    {
        if (empty($rows)) {
            return self::emptyState(
                'Nenhum dado encontrado',
                $emptyMessage ?? 'Não há registros para exibir.',
                'bi-table'
            );
        }

        $headerHtml = '';
        foreach ($columns as $col) {
            $headerHtml .= "<th>{$col}</th>";
        }

        $bodyHtml = '';
        foreach ($rows as $row) {
            $bodyHtml .= '<tr>';
            foreach ($row as $cell) {
                $bodyHtml .= "<td>{$cell}</td>";
            }
            $bodyHtml .= '</tr>';
        }

        return <<<HTML
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>{$headerHtml}</tr>
                </thead>
                <tbody>
                    {$bodyHtml}
                </tbody>
            </table>
        </div>
        HTML;
    }

    /**
     * Score circle
     */
    public static function scoreCircle(int $score, string $size = 'md'): string
    {
        $color = $score >= 70 ? 'success' : ($score >= 50 ? 'warning' : 'danger');
        $sizes = ['sm' => '60px', 'md' => '80px', 'lg' => '100px'];
        $sizeVal = $sizes[$size] ?? $sizes['md'];
        $fontSize = $size === 'sm' ? '1.25rem' : ($size === 'lg' ? '2rem' : '1.5rem');

        return <<<HTML
        <div class="score-circle score-{$color}" style="width: {$sizeVal}; height: {$sizeVal};">
            <span class="score-value" style="font-size: {$fontSize};">{$score}</span>
        </div>
        HTML;
    }
}
