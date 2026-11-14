<?php

namespace App\Admin\Grid\Filter;

use Dcat\Admin\Grid\Filter\Presenter\DateTime;
use Illuminate\Support\Arr;
use Dcat\Admin\Grid\Filter\Between as FilterBetween;

class Between extends FilterBetween
{
        /**
     * Get condition of this filter.
     *
     * @param  array  $inputs
     * @return mixed
     */
    public function condition($inputs)
    {
        if (! Arr::has($inputs, $this->column)) {
            return;
        }

        $this->value = Arr::get($inputs, $this->column);

        $value = array_filter($this->value, function ($val) {
            return $val !== '';
        });

        if ($this->timestamp) {
            $value = array_map(function ($v) {
                if ($v) {
                    return strtotime($v);
                }
            }, $value);
        }

        if (empty($value)) {
            return;
        }

        if (! isset($value['start']) && isset($value['end'])) {
            return $this->buildCondition($this->column, '<=', $value['end']);
        }

        if (! isset($value['end']) && isset($value['start'])) {
            return $this->buildCondition($this->column, '>=', $value['start']);
        }

        $this->query = 'whereBetween';

        // 自定义起始、结束时间
        $value['start'] = $value['start'] . " 00:00:00";
        $value['end'] = $value['end'] . " 23:59:59";

        return $this->buildCondition($this->column, [$value['start'], $value['end']]);
    }
}