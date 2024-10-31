<?php /** @noinspection SpellCheckingInspection */


namespace rnpdfimporter\core\Integration\Adapters\NinjaForms\FormProcessor;


use rnpdfimporter\core\Integration\Processors\FormProcessor\FormProcessorBase;
use rnpdfimporter\core\Integration\Processors\Settings\Forms\EmailNotification;
use rnpdfimporter\core\Integration\Processors\Settings\Forms\Fields\FieldSettingsBase;
use rnpdfimporter\core\Integration\Processors\Settings\Forms\Fields\FileUploadFieldSettings;
use rnpdfimporter\core\Integration\Processors\Settings\Forms\Fields\MultipleOptionsFieldSettings;
use rnpdfimporter\core\Integration\Processors\Settings\Forms\Fields\TextFieldSettings;
use rnpdfimporter\core\Integration\Processors\Settings\Forms\FormSettings;
use rnpdfimporter\core\Utils\JSONSanitizer;
use stdClass;

class NinjaFormProcessor extends FormProcessorBase
{

    public function __construct($loader)
    {
        parent::__construct($loader);
        \add_action('ninja_forms_save_form',array($this,'FormIsSaving'),10);
    }

    public function FormIsSaving($formId){
        if(isset($forms['post_content']))
        {
            $forms['post_content'] = \stripslashes($forms['post_content']);
            $forms = $this->SerializeForm($forms);
        }
        else
        {
            $forms = JSONSanitizer::Sanitize(\json_decode(\stripslashes($_POST['form'])),array(
                'actions'=>JSONSanitizer::PROPERTY_ARRAY,
                'id'=>JSONSanitizer::PROPERTY_STRING
            ));
            $forms = $this->SerializeFormV2($forms);
        }
        $this->SaveOrUpdateForm($forms);
    }


    public function SerializeFormV2($form){

        $formSettings=new FormSettings();
        if(isset($form->actions))
        {
            foreach($form->actions as $currentAction)
            {
                if($currentAction->settings->type=='email')
                {
                    $formSettings->EmailNotifications[]=new EmailNotification($currentAction->id,$currentAction->settings->label);
                }
            }
        }



        $formSettings->OriginalId=$form->id;
        $formSettings->Name=$form->settings->title;
        $formSettings->Fields=$this->SerializeFieldsV2($form->fields);


        return $formSettings;
    }


    public function SerializeFieldsV2($fieldList)
    {
        /** @var FieldSettingsBase[] $fieldSettings */
        $fieldSettings=array();

        foreach($fieldList as $field)
        {
            switch($field->settings->type)
            {
                case 'textarea':
                case 'email':
                case 'textbox':
                case 'firstname':
                case 'lastname':
                case 'phone':
                case 'address':
                case 'city':
                case 'liststate':
                case 'listcountry':
                case 'zip':
                case 'date':
                case 'checkbox':
                case 'confirm':
                case 'hidden':
                case 'number':
                case 'starrating':
                    $fieldSettings[]=(new TextFieldSettings())->Initialize($field->id,$field->settings->label,$field->settings->type);
                    break;
                case 'listcheckbox':
                case 'listmultiselect':
                case 'listradio':
                case 'listselect':
                    $settings=(new MultipleOptionsFieldSettings())->Initialize($field->id,$field->settings->label,$field->settings->type);
                    if(isset($field->settings->options))
                    {
                        foreach($field->settings->options as $option)
                        {
                            $settings->AddOption($option->label,$option->value,'');
                        }
                    }
                    $fieldSettings[]=$settings;
                    break;
                case 'file_upload':
                    $fieldSettings[]=(new FileUploadFieldSettings())->Initialize($field->id,$field->settings->label,$field->settings->type);
                    break;
            }
        }

        return $fieldSettings;
    }


    public function SerializeForm($form){

        $formSettings=new FormSettings();
        $formSettings->OriginalId=$form->Id;
        $formSettings->Name=$form->Name;
        $formSettings->Fields=$this->SerializeFields($form->Fields,$form->Id);

        if(isset($form->actions))
        {
            foreach($form->actions as $currentAction)
            {
                if($currentAction->settings->type=='email')
                {
                    $formSettings->EmailNotifications[]=new EmailNotification($currentAction->id,$currentAction->settings->label);
                }
            }
        }


        return $formSettings;
    }

    public function SerializeFields($fieldList,$formId)
    {
        $originalForm=\Ninja_Forms()->form( $formId)->get_fields();
        /** @var FieldSettingsBase[] $fieldSettings */
        $fieldSettings=array();
        foreach($fieldList as $field)
        {
            switch($field->Type)
            {
                case 'textarea':
                case 'email':
                case 'textbox':
                case 'firstname':
                case 'lastname':
                case 'phone':
                case 'address':
                case 'city':
                case 'liststate':
                case 'listcountry':
                case 'zip':
                case 'date':
                case 'checkbox':
                case 'confirm':
                case 'hidden':
                case 'number':
                case 'starrating':
                    $fieldSettings[]=(new TextFieldSettings())->Initialize($field->Id,$field->Label,$field->Type);
                    break;

                case 'listcheckbox':
                case 'listmultiselect':
                case 'listselect':
                case 'listradio':
                    $settings=(new MultipleOptionsFieldSettings())->Initialize($field->Id,$field->Label,$field->Type);

                    if(isset($originalForm[$field->Id]))
                    {
                        /** @var \NF_Database_Models_Field $field */
                        $originalField=$originalForm[$field->Id];
                        $fieldOriginalSettings=$originalField->get_settings();
                        if(isset($fieldOriginalSettings['options']))
                        {
                            foreach($fieldOriginalSettings['options'] as $currentOptions)
                                $settings->AddOption($currentOptions['label'],$currentOptions['value']);
                        }
                    }
                    $fieldSettings[]=$settings;
                    break;
                case 'file_upload':
                    $fieldSettings[]=(new FileUploadFieldSettings())->Initialize($field->Id,$field->Label,$field->Type);
                    break;
            }
        }

        return $fieldSettings;
    }

    public function GetFormList()
    {
        global $wpdb;

        return $wpdb->get_results("select id Id, name Name, fields Fields,original_id OriginalId,notifications Notifications from ".$this->Loader->FormConfigTable );
    }

    public function SyncCurrentForms($formId=-1)
    {
        global $wpdb;
        $results=$wpdb->get_results($wpdb->prepare("select forms.id FormId,title Title, field.label Label,field.type Type,field.key FieldKey,field.id FieldId
                                            from ".$wpdb->prefix."nf3_forms forms
                                            join ".$wpdb->prefix."nf3_fields field
                                            on forms.id=field.parent_id
                                            where forms.id=%d or %d=-1
                                            order by FormId",$formId,$formId));

        $actions=$wpdb->get_results('select id,parent_id, type, label from '.$wpdb->prefix.'nf3_actions where type="email"');

        $currentForm=null;
        $formIds=array();
        foreach($results as $form)
        {
            if($currentForm==null|| $form->FormId!=$currentForm->Id)
            {
                $formIds[]=$form->FormId;
                if($currentForm!=null)
                {
                    $formToSave=$this->SerializeForm($currentForm);
                    $this->SaveOrUpdateForm($formToSave);
                }
                $currentForm=new stdClass();
                $currentForm->Id=$form->FormId;
                $currentForm->Fields=[];
                $currentForm->Name=$form->Title;

                $currentForm->actions=[];
                foreach($actions as $currentAction)
                {
                    if($currentAction->parent_id==$currentForm->Id)
                    {
                        $currentForm->actions[]=(object)array(
                            'id'=>$currentAction->id,
                            'settings'=>(object)array(
                                'label'=>$currentAction->label,
                                'type'=>'email'
                            )
                        );
                    }
                }

            }

            $field=new stdClass();
            $currentForm->Fields[]=$field;
            $field->Id=$form->FieldId;
            $field->Type=$form->Type;
            $field->Label=$form->Label;
            $field->Key=$form->FieldKey;



            //    $this->SaveOrUpdateForm($form);
        }

        if($currentForm!=null)
        {
            $form=$this->SerializeForm($currentForm);
            $this->SaveOrUpdateForm($form);
        }

        $how_many = count($formIds);
        $placeholders = array_fill(0, $how_many, '%d');
        $format = implode(', ', $placeholders);

        $query = "delete from ".$this->Loader->FormConfigTable." where original_id not in($format)";
        $wpdb->query($wpdb->prepare($query,$formIds));
    }
}