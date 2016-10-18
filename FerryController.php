<?php

class FerryController extends Controller {
    /**
     * Declares class-based actions.
     */
    public $layout = '//../front/layouts/general';
    public $renderBootstrapDatePicker = false;

    public function filters() {
        return array(
            'accessControl',
        );
    }


    public function actions() 
    {
        return array(
            // captcha action renders the CAPTCHA image displayed on the contact page
            'captcha' => array(
                'class' => 'CCaptchaAction',
                'backColor' => 0xFFFFFF,
            ),
            // page action renders "static" pages stored under 'protected/views/site/pages'
            // They can be accessed via: index.php?r=site/page&view=FileName
            'page' => array(
                'class' => 'CViewAction',
            ),
        );
    }

    public function accessRules()
    {
        return array(
            array('allow',
                'actions' => array('lastFerryBooking','installationDetail','installationDetailList','routeOfferDetail','portPage','listPortPage'),
                'users' => array('*'),
            ),
        );
    }



// display company list + journies : /ferry-traversees
    public function actionIndex()
    {

        $this->render('index', array(
        ));
    }


    // display company list + journies : /ferry-traversees/
    public function actionDetail($name, $id)
    {


        $company = Compagnies::model()->findByPk(intval($id));
        //$journeyModel = Journey::model()->findByPk(intval($id));

        if ($company === null) {
            throw new CHttpException(404, Yii::t('main', '404 page not found'));
        }

        // get company active journeys
        $journeys = $company->journeys('journeys:active');

       // App::p($journeys); exit;
        $dataProvider = new CArrayDataProvider($journeys, array(
            'pagination' => false, // disable pagination
        ));

        $this->render('detail', array(
            'company' => $company,
            'dataProvider' => $dataProvider,
            'companyName' => $company->name,
        ));
    }

    public function actionZonePage()
    {
        $zone = strtoupper($_GET['zoneName']);
        $zoneId = PropertyValue::enumConstantId("ZoneEnumerable", $zone, true, false);
        
        if ($zoneId === null) {
            throw new CHttpException(404, Yii::t('main', '404 page not found'));
        }

        $journies = AR::getJourneysByZoneId($zoneId);
        $depart_port_ids = array();
        $arrive_port_ids = array();
        foreach ($journies as $key => $journey) {
            $depart_port_ids[] = $journey->depart_port_id;
            $arrive_port_ids[] = $journey->arrive_port_id;
        }
        $criteria = new CDbCriteria;
        $criteria->limit = 6;
        $criteria->compare('currency', Yii::app()->user->currency);
        $criteria->addInCondition('depart_port_id', $depart_port_ids);
        $criteria->addInCondition('arrive_port_id', $arrive_port_ids);
        $ws_journey = WsJourney::model()->findAll($criteria);
        
        $this->render('zone', array(
            'zone' => strtolower($zone),
            'journies' => $journies,
            'ws_journey' => $ws_journey,
        ));
    }

    public function actionCountryPage($id)
    {
        $country = CountryList::model()->findByPk(intval($id));
        if ($country === null) {
            throw new CHttpException(404, Yii::t('main', '404 page not found'));
        }

        $journies = AR::getJourneysByCountryId($id);
        $this->render('country', array(
            'country' => $country,
            'journies'  => $journies,
        ));
    }

    public function actionPortPage($id)
    {

        $port = Ports::model()->findByPk(intval($id));

        if ($port === null) {
            throw new CHttpException(404, Yii::t('main', '404 page not found'));
        }
        $this->render('port', array(
            'port' => $port,
        ));
    }

    public function actionlistPortPage()
    {

        $ports = Ports::model()->findAll('application_type_id =:id', array(
            ':id' => 2
        ));

        //App::p($ports); exit;
        if ($ports === null) {
            throw new CHttpException(404, Yii::t('main', '404 page not found'));
        }
        $this->render('list_port', array(
            'ports' => $ports,
        ));
    }


    public function actionLastFerryBooking()
    {

        $criteria = new CDbCriteria;
        $criteria->limit = 20;
        $criteria->compare('domain_id', Yii::app()->domainManager->getData()->id);
        $criteria->compare('application_type_id', AppTypeEnumerable::getAppTypeId('Ferry'));
        $criteria->order = "reservation_date desc";
        $bookings = WsBooking::model()->findAll($criteria);

        $this->render('lastbooking', array(
            'bookings' => $bookings
        ));
    }


    // accomodation, classes
    public function actionInstallation()
    {

        $criteria = new CDbCriteria;
        $criteria->condition = 'activation=1 AND application_type_id=' . AppTypeEnumerable::getAppTypeId('Ferry');
        $compagnies = Compagnies::model()->findAll($criteria);

        $this->render('installation', array(
            'compagnies' => $compagnies
        ));

    }



    public function actionInstallationDetailList($name, $id)
    {


        $company = Compagnies::model()->findByPk($id);

       //App::p($id); exit;


        $accomodation = Accommodation::model()->findAllByAttributes(array('company_id'=>$id));

        $this->render('installationDetailList', array(
            'company' => $company,
            'accomodation' => $accomodation

        ));

    }


    // accomodation, classes
    public function actionInstallationDetail($id)
    {
        $accomodation = Accommodation::model()->findByPk($id);
        $company = Compagnies::model()->findByPk($accomodation->company_id);

        $accomodation_images = Yii::app()->db->createCommand( "select name from `photos` inner join photos_accommodation pha on (pha.Idphotos = photos.id) where Idaccommodation = '".$id."'")->queryAll();



        $this->render('installationDetail', array(
            'company' => $company,
            'accomodation' => $accomodation,
            'accomodation_images' => $accomodation_images

        ));

    }


    /**
     * This is the action to handle external exceptions.
     */
    public function actionError() 
    {
        if (null !== ($error = Yii::app()->errorHandler->error)) {
            if (Yii::app()->request->isAjaxRequest) {
                echo $error['message'];
            } else {
                $this->render('error', $error);
            }
        }
    }


  // view : themes/safar24/views/ferry/show.php
    public function actionShow($route, $id)
    {
        $journeyModel = Journey::model()->findByPk(intval($id));
        if ($journeyModel === null) {
            throw new CHttpException(404, Yii::t('main', '404 page not found'));
        }

        $model = new FerryBooking();
        $model->journey_id = $journeyModel->id;

        if (!empty($journeyModel)) {

            $this->render('show', array(
                'journeyModel' => $journeyModel,
                'model' => $model,
            ));


        }
    }



    public function getCodePromoJourney($journey_id, $currency)
    {
        $codePromo = CodePromo::model()->active()->findByAttributes(
            array(
                'traverse_id' => $journey_id,
                'currency' => $currency,
            ),
            array(
                'order' => 'reduction_amount DESC'
            )
        );

        return (count($codePromo) > 0) ? $codePromo->code : NULL;
    }

    public function actionRouteOfferDetail($route, $id)
    {
        $domain = Yii::app()->domainManager->getData();
        $appType = AppTypeEnumerable::getAppTypeId('Ferry');
        $journey = Journey::model()->findByPk(intval($id));
        $relatedJourney = JourneyRelated::getRelatedJourney($journey->id);
        $depart_port_id = $journey->depart_port_id;
        $arrive_port_id= $journey->arrive_port_id;
        $company = $journey->company;

        $articles = Articles::model()->forDomain($domain->id)->findAll();
        $banners = Banner::model()->active()->forDomain($domain->id)->findAll();
        $banners = Banner::model()->active()->forDomain($domain->id)->findAllByAttributes(array(
            'position' => Banner::POSITION_HOME_HEADER,
            'application_type_id' => $appType,
            'currency' => Yii::app()->user->currency,
        ));

        $temoignages = Temoignages::model()->active()->findAll();

        $criteria = new CDbCriteria;
        $criteria->limit = 3;
        $ws_journey = WsJourney::model()->findAllByAttributes(array(
            'depart_port_id' => $depart_port_id,
            'arrive_port_id' => $arrive_port_id,
        ), $criteria);

         $ws_journeyInterest = WsJourney::model()->findAllByAttributes(array(
            'depart_port_id' => $depart_port_id,
        ), $criteria);



      /* CVarDumper::dump($ws_journey); exit;
        $this->data = $journey;*/

        //CVarDumper::dump($banners);
        //exit;
        if ($journey === null) {
            throw new CHttpException(404, Yii::t('main', '404 page not found'));
        }

        $codePromo = $this->getCodePromoJourney($id, Yii::app()->user->currency);

        $this->render('route_offer_detail', array(
            'journey' => $journey,
            'company' => $company,
            'articles' => $articles,
            'banners' => $banners,
            'domain' => $domain,
            'codePromo' => $codePromo,
            'ws_journey' => $ws_journey,
            'ws_journeyInterest' => $ws_journeyInterest,
            'relatedJourney' => $relatedJourney,
            'temoignages' => $temoignages,
        ));

    }


    public function getJourneyDepartures($parms)
    {
        return Yii::app()->db->createCommand()
            ->select('*')
            ->from('departures')
            ->where('Origin = :origin AND Destination = :dest AND CompanyName = :c_id AND DepartureDateMonth = :month AND DepartureDateYear = :year',
                array(':origin' => $parms['from'] ,':dest' => $parms['to'] ,':c_id' => $parms['company_name'], ':month' => $parms['month'] , ':year' => $parms['year']))
            ->queryAll();
    }

    /*
    * Ferry journey schedule page. ex : ferry-horaires/almeria-nador/11/
    */
    public function actionSchedule($id)
    {
        $journey = Journey::model()->findByPk($id);

        if ($journey === null) {
            throw new CHttpException(404, Yii::t('main', '404 page not found'));
        }
        
        $criteria = new CDbCriteria;
        $criteria->distinct = true;
        
        $criteria->compare('journey_id', $id);
        
        $dayNow = date('d', time());
        $monthNow = date('m', time());
        $yearNow = date('Y', time());
        if (Yii::app()->request->isAjaxRequest) {
            $month = isset($_GET['month']) ? $_GET['month'] : $monthNow;
            $year = isset($_GET['year']) ? $_GET['year'] : $yearNow;
            $monthFrom = date('m', strtotime($dayNow.'-'.$month.'-'.$year));
            $yearFrom = date('Y', strtotime($dayNow.'-'.$month.'-'.$year));
            $news = strtotime($dayNow.'-'.$monthFrom.'-'.$yearFrom);
            $nows = strtotime($dayNow.'-'.$monthNow.'-'.$yearNow);
            if($news > $nows){
                $criteria->addCondition('DepartureDateDay > '.$dayNow);
                $criteria->compare('DepartureDateMonth', $monthFrom);
                $criteria->compare('DepartureDateYear', $yearFrom);
            }else{
                $criteria->compare('DepartureDateMonth', 100);
            }
        }else{
            $criteria->addCondition('DepartureDateDay > '.$dayNow);
            $criteria->compare('DepartureDateMonth', $monthNow);
            $criteria->compare('DepartureDateYear', $yearNow);
        }
        $criteria->group = 'DepartureTimeHour, DepartureTimeMinutes, DepartureDateDay, DepartureDateMonth, DepartureDateYear';
        $departures = Departures::model()->findAll($criteria);

        $departures = new CArrayDataProvider($departures, array(
            'pagination' => array(
                'pageSize' => 10,
            ),
            'sort'=>array(
                'defaultOrder' => 'DepartureDateYear, DepartureDateMonth, DepartureDateDay, DepartureTimeHour, DepartureTimeMinutes DESC',
            ),
        ));

        $this->render('_schedule_page', array(
              'departures' =>$departures,
              'journey' => $journey,
              'monthNow' => $monthNow,
         ));
    }

    /**
     * @function datefilter
     * @param title string
     */
    public function actionDatefilter()
    {
        if ($_POST['title']) {
            $slug = Yii::app()->slug->url_slug($_POST['title']);
            echo CJSON::encode(array(      
                    'slug'=>$slug,
                ));         
            Yii::app()->end();
            
        }
    }
    
    
    /**
     * Ferry journey landing page
     */
    public function actionJourneyDetail($id)
    {
        $this->layout = '//../front/layouts/ferry_landing';
        
        $journey = Journey::model()->findByPk($id);

        $this->data = $journey = Journey::model()->findByPk($journey['id']);
        $domain = Yii::app()->domainManager->data;



        $countrylist = array();
        //get the country of journey


        if ($journey) {
            $countrylist['from'] = $journey->departPort->country->name_fr;
            $countrylist['to'] = $journey->arrivePort->country->name_fr;
        }

        $model = new FerryBooking();
        $model->journey_id = $journey->id;
        $model->booking_ref = Controller::setBookingRef();
        $this->execData($model);
        if (isset($model->aller_date) && is_numeric($model->aller_date))
            $model->aller_date = date('d/m/Y', $model->aller_date);
        if (isset($model->return_date) && is_numeric($model->return_date))
            $model->return_date = date('d/m/Y', $model->return_date);
        $isSubmitted = (!empty($this->loaderData['isSubmitted']) && $this->loaderData['isSubmitted'] == true);

        

        $this->render('journey_detail', array(
            'model' => $model,
            'journey' => $journey,
            'domain' => $domain,
            'countrylist' => $countrylist,
        ));
    }

    protected function execData(&$model) {
        if (null !== ($formData = Yii::app()->request->getParam(get_class($model)))) {
            $model->setAttributes($formData);
            Yii::app()->clientScript->registerScript('i-form-search-submit', '$("#form-search-button").click();', CClientScript::POS_LOAD);
            $this->loaderData['isSubmitted'] = true;
        }
    }

}
