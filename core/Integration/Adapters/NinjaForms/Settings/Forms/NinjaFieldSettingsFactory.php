<?php


namespace rnpdfimporter\core\Integration\Adapters\NinjaForms\Settings\Forms;


use PHPMailer\PHPMailer\Exception;
use rnpdfimporter\core\Integration\Processors\Settings\Forms\FieldSettingsFactoryBase;

class NinjaFieldSettingsFactory extends FieldSettingsFactoryBase
{
    public function GetFieldByOptions($options)
    {
        $field= parent::GetFieldByOptions($options);
        if($field!=null)
            return $field;

        throw new Exception('Invalid field settings type '.$options->Type);
    }
}