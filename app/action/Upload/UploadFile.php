<?php

/**
 *  Upload/UploadFile.php
 *
 *  @author     {$author}
 *  @package    Sample
 */

/**
 *  upload_uploadFile Form implementation.
 *
 *  @author     {$author}
 *  @access     public
 *  @package    Sample
 */
class Sample_Form_UploadUploadFile extends Sample_ActionForm
{
    /**
     *  @access protected
     *  @var    array   form definition.
     */
    public $form = array(
       
       'filePath' => [
           'name'      => 'ファイルパス',
           'required'  => true,
           'type'      => VAR_TYPE_FILE,
           'file_type' => 'image/jpeg'
       ],

       'userID' => [
           'type' => VAR_TYPE_INT  
       ],

       'userName' => [
           'type' => VAR_TYPE_STRING
       ],

       'eventID' => [
           'type' => VAR_TYPE_INT
       ],

       'eventName' => [
           'type' => VAR_TYPE_STRING
       ],
       
       /*
        *  TODO: Write form definition which this action uses.
        *  @see http://ethna.jp/ethna-document-dev_guide-form.html
        *
        *  Example(You can omit all elements except for "type" one) :
        *
        *  'sample' => array(
        *      // Form definition
        *      'type'        => VAR_TYPE_INT,    // Input type
        *      'form_type'   => FORM_TYPE_TEXT,  // Form type
        *      'name'        => 'Sample',        // Display name
        *
        *      //  Validator (executes Validator by written order.)
        *      'required'    => true,            // Required Option(true/false)
        *      'min'         => null,            // Minimum value
        *      'max'         => null,            // Maximum value
        *      'regexp'      => null,            // String by Regexp
        *
        *      //  Filter
        *      'filter'      => 'sample',        // Optional Input filter to convert input
        *      'custom'      => null,            // Optional method name which
        *                                        // is defined in this(parent) class.
        *  ),
        */
    );

    /**
     *  Form input value convert filter : sample
     *
     *  @access protected
     *  @param  mixed   $value  Form Input Value
     *  @return mixed           Converted result.
     */
    /*
    protected function _filter_sample($value)
    {
        //  convert to upper case.
        return strtoupper($value);
    }
    */
}

/**
 *  upload_uploadFile action implementation.
 *
 *  @author     {$author}
 *  @access     public
 *  @package    Sample
 */
class Sample_Action_UploadUploadFile extends Sample_ActionClass
{
    /**
     *  preprocess of upload_uploadFile Action.
     *
     *  @access public
     *  @return string    forward name(null: success.
     *                                false: in case you want to exit.)
     */
    public function prepare()
    {
        if ($this->af->validate() > 0){
		return 'upload';
	}
        return null;
    }

    /**
     *  upload_uploadFile action implementation.
     *
     *  @access public
     *  @return string  forward name.
     */
    public function perform()
    {
        include('adodb/adodb.inc.php');
        
        // S3の設定(バケット,　アクセスキー,　シークレットキー)を取得
        include_once('/home/m17/m17-miya/sen.rmiyamoto/conf/setting.php');

        $uploaddir = '/home/m17/m17-miya/sen.rmiyamoto/tempupload';
        $uploadfile = $uploaddir . '/'  . basename($_FILES['filePath']['name']);

        // ファイルの移動
        if (!move_uploaded_file($_FILES['filePath']['tmp_name'], $uploadfile)){
            return  Ethna::raiseNotice('ファイルがアップロードできませんでした',E_SAMPLE_AUTH);
        }

        // イベント名とファイル名を取得        
        //$eventName = $this->session->get('eventname');
        $eventName = $_POST["eventName"];
        $fileName = $_FILES['filePath']['name'];
        
        $um = new Sample_UserManager();
        
        // イベントIDの取得
        //$eventID = $um->getEventID($eventName, $this->backend);
        $eventID = $_POST["eventID"];

        // DBにアップロードしたファイル情報（イベントID、ファイル名）を登録しファイルIDを取得
        //$fileID = $um->addPhotoDataDB($eventID["event_id"], $fileName ,$this->backend);
        $fileID = $um->addPhotoDataDB($eventID, $fileName ,$this->backend);
        if (Ethna::isError($fileID)) {
                $this->ae->addObject(null, $fileID);
        }
        
        // 以降S3へのアップロード
        // アップデートするファイルの情報やS3の設定を配列に入れ込む
        $uploadData = [
            "s3Conf" => $s3Conf,
            "fileInfo" => [
                "fileID"    => $fileID["photo_id"],
                "filePath"  => $uploadfile,
                "type"      => 'image/jpeg',
                //"eventID"   => $eventID["event_id"]
                "eventID"   => $eventID
            ]
        ];	

        // ファイルのアップデート
        $um->uploadFileS3($uploadData);

        // 一時ファイルの削除
        unlink($uploadfile);
        
        // アップロード完了のフラグをtplに渡す
        $this->af->setApp('uploadComp', true);

        return 'upload';
    }
}
