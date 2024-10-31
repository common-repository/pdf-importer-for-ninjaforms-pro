<?php


namespace rnpdfimporter\core\Integration\Adapters\NinjaForms\Entry\Retriever;


use rnpdfimporter\core\Integration\Adapters\NinjaForms\Entry\NinjaEntryProcessor;
use rnpdfimporter\core\Integration\Adapters\NinjaForms\Settings\Forms\NinjaFieldSettingsFactory;
use rnpdfimporter\core\Integration\Processors\Entry\EntryProcessorBase;
use rnpdfimporter\core\Integration\Processors\Entry\Retriever\EntryRetrieverBase;

class NinjaFormsRetriever extends EntryRetrieverBase
{

    public function GetFieldSettingsFactory()
    {
        return new NinjaFieldSettingsFactory();
    }

    /**
     * @return EntryProcessorBase
     */
    protected function GetEntryProcessor()
    {
        return new NinjaEntryProcessor($this->Loader);
    }

    public function GetProductItems()
    {
        return array();
    }
}