<?php

namespace RodrigoPedra\RecordProcessor\Helpers\Writers;

use InvalidArgumentException;
use RodrigoPedra\RecordProcessor\Helpers\Configurator;
use RodrigoPedra\RecordProcessor\Traits\Writers\HasHeader;
use RodrigoPedra\RecordProcessor\Contracts\RecordFormatter;
use RodrigoPedra\RecordProcessor\Traits\Writers\HasTrailler;
use RodrigoPedra\RecordProcessor\Contracts\ConfigurableWriter;
use RodrigoPedra\RecordProcessor\Records\Formatter\ArrayRecordFormatter;
use RodrigoPedra\RecordProcessor\Records\Formatter\CallbackRecordFormatter;

/**
 * Class WriterConfigurator
 *
 * @package RodrigoPedra\RecordProcessor\Helpers
 */
class WriterConfigurator extends Configurator
{
    use HasHeader, HasTrailler;

    /** @var  bool */
    protected $hasHeader;

    /** @var  bool */
    protected $hasTrailler;

    /** @var  RecordFormatter|null */
    protected $recordFormatter = null;

    public function __construct(ConfigurableWriter $writer, $hasHeader = false, $hasTrailler = false)
    {
        parent::__construct($writer);

        $this->hasHeader = $hasHeader;
        $this->hasTrailler = $hasTrailler;
    }

    public function getRecordFormatter(RecordFormatter $defaultRecordFormatter = null)
    {
        if (is_null($this->recordFormatter)) {
            return $defaultRecordFormatter ?: new ArrayRecordFormatter;
        }

        return $this->recordFormatter;
    }

    /**
     * @param  RecordFormatter|callable  $recordFormatter
     */
    public function setRecordFormatter($recordFormatter)
    {
        if (is_callable($recordFormatter)) {
            $this->recordFormatter = new CallbackRecordFormatter($recordFormatter);

            return;
        }

        if (! $recordFormatter instanceof RecordFormatter) {
            throw new InvalidArgumentException('Formatter should implement RecordFormatter interface');
        }

        $this->recordFormatter = $recordFormatter;
    }

    public function setHeader($header)
    {
        if (! $this->hasHeader) {
            $className = get_class($this->configurable);

            throw new InvalidArgumentException("The writer '{$className}' does not accept a header");
        }

        $this->header = $header;
    }

    public function setTrailler($trailler)
    {
        if (! $this->hasTrailler) {
            $className = get_class($this->configurable);

            throw new InvalidArgumentException("The writer '{$className}' does not accept a trailler");
        }

        $this->trailler = $trailler;
    }
}
