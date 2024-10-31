<?php


namespace rnpdfimporter\core\Integration\Adapters\NinjaForms\Entry;


use rnpdfimporter\core\Integration\Adapters\NinjaForms\Entry\Retriever\NinjaFormsRetriever;
use rnpdfimporter\core\Integration\Processors\Entry\EntryItems\DropDownEntryItem;
use rnpdfimporter\core\Integration\Processors\Entry\EntryItems\EntryItemBase;
use rnpdfimporter\core\Integration\Processors\Entry\EntryItems\FileUploadEntryItem;
use rnpdfimporter\core\Integration\Processors\Entry\EntryItems\SimpleTextEntryItem;
use rnpdfimporter\core\Integration\Processors\Entry\EntryProcessorBase;
use rnpdfimporter\JPDFGenerator\JPDFGenerator;
use rnpdfimporter\pr\Managers\ConditionManager\ConditionManager;
use stdClass;

class NinjaEntryProcessor extends EntryProcessorBase
{

    public function __construct($loader)
    {
        parent::__construct($loader);
        \add_action('nf_save_sub',array($this,'SaveEntry'));
        \add_action('ninja_forms_action_email_attachments',array($this,'AddAttachment'),10,3);

    }



    public function SaveEntry($entryId){
        global $wpdb;
        $entry=$wpdb->get_results($wpdb->prepare('select meta_key Id, meta_value Value from '.$wpdb->postmeta.' where post_id=%d',$entryId));
        if($entry==null)
            return;

        $dictionary=new stdClass();
        foreach($entry as $row)
        {
            $id=$row->Id;
            $dictionary->$id=$row->Value;
        }



        $serializedEntry=$this->SerializeEntry($dictionary);
        if($serializedEntry==null)
            return;
        $entryId=$this->SaveEntryToDB($dictionary->{'_form_id'},$serializedEntry['Items'],$entryId);
        global $RNWPImporterCreatedEntry;
        $RNWPImporterCreatedEntry=array(
            'Entry'=>$serializedEntry['Items'],
            'FormId'=>$dictionary->{'_form_id'},
            'OriginalId'=>$dictionary->{'_form_id'},
            'EntryId'=>$entryId,
            'Raw'=>$dictionary
        );

        $_SESSION['Ninja_Generated_PDF']=array(
            'EntryId'=>$entryId,
            'FormId'=>$dictionary->{'_form_id'}
        );
    }

    public function AddAttachment($attachment,$target,$settings)
    {
        global $RNWPImporterCreatedEntry;
        if(!isset($RNWPImporterCreatedEntry)||!isset($RNWPImporterCreatedEntry['Entry']))
        {
            $dictionary=new stdClass();
            $dictionary->_form_id=$target['form_id'];
            foreach ($target['fields'] as $fieldId=>$fieldValue) {
                $id='_field_'.$fieldId;
                $dictionary->$id=$fieldValue['value'];
            }
            $serializedEntry=$this->SerializeEntry($dictionary);
            if($serializedEntry==null)
                return;
            $entry=$serializedEntry['Items'];
            $raw=$dictionary;
            $formId=$target['form_id'];
            $entryId=0;
        }else {

            $entry = $RNWPImporterCreatedEntry['Entry'];
            $raw = $RNWPImporterCreatedEntry['Raw'];
            $formId=$RNWPImporterCreatedEntry['FormId'];
            $entryId=$RNWPImporterCreatedEntry['EntryId'];
        }
        $raw= json_decode(json_encode($raw));

        global $wpdb;
        $fields=$wpdb->get_var($wpdb->prepare('select fields from '.$this->Loader->FormConfigTable.' where original_id=%s',$formId));


        $entryRetriever=new NinjaFormsRetriever($this->Loader);
        $entryRetriever->InitializeByEntryItems($entry,$raw,$fields);

        global $wpdb;
        $result=$wpdb->get_results($wpdb->prepare(
            "select template.id Id,attach_to_email AttachToEmail,skip_condition SkipCondition 
                    from ".$this->Loader->FormConfigTable." form
                    join ".$this->Loader->PDFImporterTable." template
                    on form.id=template.form_used
                    where original_id=%s"
            ,$formId));
        if(!isset($RNWPImporterCreatedEntry['CreatedDocuments'])){
            $RNWPImporterCreatedEntry['CreatedDocuments']=[];
        }


        foreach($result as $templateSettings)
        {

            if($this->Loader->IsPR()&&isset($templateSettings->SkipCondition))
            {
                $condition=json_decode($templateSettings->SkipCondition);
                $conditionManager=new ConditionManager();
                if($conditionManager->ShouldSkip($this->Loader, $entryRetriever,$condition))
                {
                    continue;
                }
            }

            $templateSettings->AttachToEmail=\json_decode($templateSettings->AttachToEmail);

            $generator=new JPDFGenerator($this->Loader);
            $generator->LoadByTemplateId($templateSettings->Id);
            $generator->LoadEntry($entryRetriever);
            $path=$generator->SaveInTempFolder();


            if(count($templateSettings->AttachToEmail)>0&&$this->Loader->IsPR())
            {
                global $WPFormEmailBeingProcessed;
                if(isset($WPFormEmailBeingProcessed))
                {
                    $found=false;
                    foreach($templateSettings->AttachToEmail as $attachToNotification)
                    {
                        if($this->Loader->PRLoader->ShouldProcessEmail($attachToNotification,$WPFormEmailBeingProcessed))
                            $found=true;


                    }

                    if(!$found)
                        continue;
                }
            }


            $RNWPImporterCreatedEntry['CreatedDocuments'][]=array(
                'TemplateId'=>$generator->Options->Id,
                'Name'=>$generator->GetFileName()
            );
            $emailData['attachments'][]=$path;
            $_SESSION['Gravity_Generated_PDF']=array(
                'TemplateId'=>$generator->Options->Id,
                'EntryId'=>$entryId
            );

            $attachment[]=$path;

        }

        return $attachment;

    }

    public function SerializeEntry($entry)
    {
        $formSettings=$this->Loader->ProcessorLoader->FormProcessor->GetFormByOriginalId($entry->_form_id);
        if($formSettings==null)
            return null;
        /** @var EntryItemBase $entryItems */
        $entryItems=array();
        foreach($formSettings->Fields as $field)
        {
            if(!isset($entry->{'_field_'.$field->Id}))
                continue;
            $currentEntry=$entry->{'_field_'.$field->Id};
            switch($field->SubType)
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
                case 'confirm':
                case 'hidden':
                case 'number':
                case 'starrating':
                    $entryItems[]=(new SimpleTextEntryItem())->Initialize($field)->SetValue($currentEntry);
                    break;
                case 'checkbox':
                    if($currentEntry=='1')
                        $currentEntry=$field->Label;
                    else
                        $currentEntry='';
                    $entryItems[]=(new SimpleTextEntryItem())->Initialize($field)->SetValue($currentEntry);
                    break;
                case 'listcheckbox':
                case 'listmultiselect':
                    if(is_array($currentEntry))
                        $options=$currentEntry;
                    else
                        $options=\unserialize($currentEntry);
                    if($options==null)
                        break;
                    $entryItems[]=(new DropDownEntryItem())->Initialize($field)->SetValue($options);
                    break;
                case 'listradio':
                case 'listselect':
                    $item=(new DropDownEntryItem())->Initialize($field);
                    $item->AddItem($currentEntry,0);

                    $entryItems[]=$item;
                    break;
                case 'file_upload':
                    $url=\array_values (\unserialize($currentEntry));


                    $item=(new FileUploadEntryItem())->Initialize($field)->SetURL($url);
                    $entryItems[]=$item;
                    break;

            }
        }


        return array(
            "Items"=>$entryItems,
            "FormId"=>$formSettings->Id
        );
    }


    public function InflateEntryItem($field,$entryData)
    {
        $entryItem=null;
        switch($field->SubType)
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
            case 'date-time':
            case 'checkbox':
            case 'listradio':
            case 'listselect':
            case 'confirm':
            case 'hidden':
            case 'number':
            case 'starrating':
                $entryItem=new SimpleTextEntryItem();
                break;

            case 'listcheckbox':
            case 'listmultiselect':
                $entryItem=new DropDownEntryItem();
                break;
            case 'file_upload':
                $entryItem=new FileUploadEntryItem();
                break;
        }

        if($entryItem==null)
            throw new \Exception("Invalid entry sub type ".$field->SubType);
        $entryItem->InitializeWithOptions($field,$entryData);
        return $entryItem;
    }
}