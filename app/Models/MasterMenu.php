<?php

namespace App\Models;

use Dcat\Admin\Traits\ModelTree;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\EloquentSortable\Sortable;

class MasterMenu extends Base implements Sortable
{
    use ModelTree {
            allNodes as treeAllNodes;
            ModelTree::boot as treeBoot;
        }

    /**
     * @var array
     */
    protected $sortable = [
        'sort_when_creating' => true,
    ];

    /**
     * Get all elements.
     *
     * @param  bool  $force
     * @return static[]|\Illuminate\Support\Collection
     */
    public function allNodes(bool $force = false)
    {
        if ($force || $this->queryCallbacks) {
            return $this->fetchAll();
        }

        return $this->fetchAll();
    }

    /**
     * Fetch all elements.
     *
     * @return static[]|\Illuminate\Support\Collection
     */
    public function fetchAll()
    {
        return $this->treeAllNodes();
    }
}
