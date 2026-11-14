<?php

namespace App\Models;

class ChartMaster extends Base
{
    /**
     * 明细属于一位达人
     */
    public function master()
    {
        return $this->belongsTo(Master::class, "master_id", "id");
    }
}
