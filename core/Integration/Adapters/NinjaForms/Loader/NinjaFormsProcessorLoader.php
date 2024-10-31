<?php


namespace rnpdfimporter\core\Integration\Adapters\NinjaForms\Loader;


use rnpdfimporter\core\Integration\Adapters\NinjaForms\Entry\NinjaEntryProcessor;
use rnpdfimporter\core\Integration\Adapters\NinjaForms\FormProcessor\NinjaFormProcessor;
use rnpdfimporter\core\Integration\Processors\Loader\ProcessorLoaderBase;

class NinjaFormsProcessorLoader extends ProcessorLoaderBase
{

    public function Initialize()
    {

        $this->FormProcessor=new NinjaFormProcessor($this->Loader);
        $this->EntryProcessor=new NinjaEntryProcessor($this->Loader);
    }
}