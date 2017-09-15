<?php

namespace RodrigoPedra\RecordProcessor\Traits\Writers;

use InvalidArgumentException;
use RodrigoPedra\RecordProcessor\Exceptions\InvalidAddonException;
use RodrigoPedra\RecordProcessor\Helpers\Writers\WriterAddon;

trait HasHeader
{
    /** @var WriterAddon|null */
    protected $header = null;

    public function getHeader()
    {
        return $this->header;
    }

    public function setHeader( $header )
    {
        try {
            $this->header = new WriterAddon( $header );
        } catch ( InvalidAddonException $ex ) {
            throw new InvalidArgumentException( 'Writer header should be an array or an callable' );
        }

        return $this;
    }
}
