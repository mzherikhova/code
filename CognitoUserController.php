<?php

namespace app\modules\api\controllers;

use Yii;
use yii\filters\VerbFilter;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use app\models\CognitoUser;

class CognitoUserController extends BaseController
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();

        $behaviors['verbFilter'] = [
            'class' => VerbFilter::className(),
            'actions' => [
                'index' => ['get'],
                'set-profile-service-id' => ['post']
            ]
        ];

        return $behaviors;
    }

    public function actionIndex()
    {
        return ['description' => 'Welcome to Cognito users management API'];
    }

    public function actionSetProfileServiceId()
    {
        $params = Yii::$app->request->bodyParams;

        if (!isset($params['pid']) || !isset($params['username'])) {
            throw new BadRequestHttpException('Missing required parameters');
        }

        $model = CognitoUser::findIdentity($params['username']);

        if (!$model) {
            throw new NotFoundHttpException('Cognito user with such username was not found.');
        }

        $model->profile_id = $params['pid'];

        return ['stauts' => $model->save()];
    }
}
