<?php

class PropertiesRetailController extends FrontEndController
{
	/**
	 * @var string the default layout for the views. Defaults to '//layouts/column2', meaning
	 * using two-column layout. See 'protected/views/layouts/column2.php'.
	 */
	public $brokerlist;
    public $metrolist;
	public $layout='//layouts/main';

    public function init()
	{
		$this->setSettings();
	}
	/**
	 * @return array action filters
	 */
	public function filters()
	{
		return array(
			'accessControl', // perform access control for CRUD operations
			'postOnly + delete', // we only allow deletion via POST request
		);
	}
    public function actions()
    {
        return array(
            'uploadImage' => array(
                'class' => 'image.components.UploadAction',
                'entity' => 'PropertiesRetail',
                'tag' => 'image',
                'directory' => 'Retail',
                'view' => 'image.components.widgets.views.images',
                'multiple' => true,
            ),
            'index' => array(
                'class' => 'property.controllers.actions.IndexAction',
                'modelClass' => 'PropertiesRetailRooms'
            ),
            'view' => array(
                'class' => 'property.controllers.actions.ViewAction',
            ),
            'listPrint' => array(
                'class' => 'property.controllers.actions.PrintAction',
            ),
            'listDownload' => array(
                'class' => 'property.controllers.actions.DownloadAction',
            ),
            'fav' => array(
                'class' => 'property.controllers.actions.FavAction',
                'modelClass' => 'PropertiesRetailRooms',
                'mode'=>0,
            ),
        );
    }
	/**
	 * Specifies the access control rules.
	 * This method is used by the 'accessControl' filter.
	 * @return array access control rules
	 */
	public function accessRules()
	{
		return array(
			array('allow',  // allow all users to perform 'index' and 'view' actions
				'actions'=>array('index','view','uploadImage','upload', 'favorites','fav', 'listPrint', 'listDownload'),
				'users'=>array('*'),
			),
			array('allow', // allow authenticated user to perform 'create' and 'update' actions
				'actions'=>array('create','update'),
				'users'=>array('@'),
			),
			array('allow', // allow admin user to perform 'admin' and 'delete' actions
				'actions'=>array('admin','delete'),
				'users'=>array('admin'),
			),
			array('deny',  // deny all users
				'users'=>array('*'),
			),
		);
	}



	/**
	 * Returns the data model based on the primary key given in the GET variable.
	 * If the data model is not found, an HTTP exception will be raised.
	 * @param integer $id the ID of the model to be loaded
	 * @return PropertiesRetail the loaded model
	 * @throws CHttpException
	 */
	public function loadModel($id)
	{
		$model=PropertiesRetailRooms::model()->findByPk($id);
		if($model===null)
			throw new CHttpException(404,'The requested page does not exist.');
		return $model;
	}

	/**
	 * Performs the AJAX validation.
	 * @param PropertiesRetail $model the model to be validated
	 */
	protected function performAjaxValidation($model)
	{
		if(isset($_POST['ajax']) && $_POST['ajax']==='properties-retail-form')
		{
			echo CActiveForm::validate($model);
			Yii::app()->end();
		}
	}

	 private function setSettings()
	{
	    $brokerlist = User::model()->findAll();
        foreach ($brokerlist as $broker){
           $this->brokerlist[$broker->id] = $broker->full_name;
        }
        $metrolist = Metro::model()->findAll();
        foreach ($metrolist as $metro){
           $this->metrolist[$metro->id] = $metro->name;
        }

	}

    public function currentRate()
    {
        $url = 'http://www.cbr.ru/scripts/XML_daily.asp';
        $curr_name = array('RUB', 'USD', 'EUR');
        $reader = New XMLReader();
        $reader->open($url);
        $i = 0;
        $output = array();
        while ($reader->read()) {
            if ($reader->nodeType == XMLReader::ELEMENT) {
                if ($reader->localName == 'CharCode') {
                    $reader->read();
                    $name = $reader->value;
                }
                if(isset($name))
                    if (in_array($name, $curr_name) && $reader->localName == 'Value') {
                        $reader->read();
                        $output[$name] = str_replace(",", ".", $reader->value);
                    }
            }
        }
        return $output;
    }
}
