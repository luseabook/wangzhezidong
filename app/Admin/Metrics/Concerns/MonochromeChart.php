<?php

namespace App\Admin\Metrics\Concerns;

/**
 * 黑白非线性皮肤下的 ApexCharts 配置。
 *
 * 在 UI_SKIN=mono 时把线条、面积、网格和坐标文字压成单色；
 * classic 皮肤保持原有配色，不做任何改动。
 */
trait MonochromeChart
{
    protected function applyMonoChart(array $colors = ['#0a0a0a', '#5c5c5a', '#8a8a86'])
    {
        if (config('app.ui_skin') !== 'mono') {
            return $this;
        }

        $this->chartOption('colors', $colors);
        $this->chartOption('stroke.curve', 'smooth');
        $this->chartOption('stroke.width', 2);
        $this->chartOption('fill.opacity', 0.06);
        $this->chartOption('chart.toolbar.show', false);
        $this->chartOption('grid.borderColor', '#e7e7e3');
        $this->chartOption('grid.strokeDashArray', 2);
        $this->chartOption('grid.xaxis.lines.show', false);
        $this->chartOption('grid.yaxis.lines.show', true);
        $this->chartOption('xaxis.labels.style.colors', '#8a8a86');
        $this->chartOption('markers.size', 3);
        $this->chartOption('markers.strokeWidth', 2);
        $this->chartOption('legend.labels.colors', '#5c5c5a');

        return $this;
    }
}