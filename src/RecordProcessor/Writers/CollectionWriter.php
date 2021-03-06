<?php

namespace RodrigoPedra\RecordProcessor\Writers;

use Illuminate\Support\Collection;
use RodrigoPedra\RecordProcessor\Contracts\Writer;

class CollectionWriter implements Writer
{
    /** @var Collection */
    protected $collection;

    public function open()
    {
        $this->collection = new Collection;
    }

    public function close()
    {
        //
    }

    public function append($row)
    {
        $this->collection->push($row);
    }

    /**
     * @return  int
     */
    public function getLineCount()
    {
        return $this->collection->count();
    }

    /**
     * @return Collection
     */
    public function output()
    {
        return $this->collection ?: new Collection;
    }
}
