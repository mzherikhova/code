<?php
namespace app\components;

use app\models\CognitoUser;
use Yii;
use yii\db\Query;

class AWSProfileServiceManager extends yii\db\Connection
{
    const CACHE_DURATION = 600;

    public function getPatientInfo($id)
    {
        return $this->getCachedUser($id, 'patients');
    }

    public function getDoctorInfo($id)
    {
        return $this->getCachedUser($id, 'doctors');
    }

    public function getCachedUser($id, $table)
    {
        $cached_info = Yii::$app->cache->get('ps-user-' . $table . '-info-' . $id);

        if ($cached_info) {
            return $cached_info;
        }

        $result = (new Query())
            ->from($table)
            ->where(['id' => $id])
            ->one($this);

        if (!$result) {
            return null;
        }

        Yii::$app->cache->set('ps-user-' . $table . '-info-' . $id, $result, self::CACHE_DURATION);

        return $result;
    }

    public function getUserInfo(CognitoUser $user)
    {
        // Todo: change to roles

        switch ($user->pool_id) {
            case Yii::$app->params['AWS-Cognito']['doctor_pool_id']: {
                return $this->getDoctorInfo($user->profile_id);
            } break;
            case Yii::$app->params['AWS-Cognito']['patient_pool_id']: {
                return $this->getPatientInfo($user->profile_id);
            } break;
        }

        return [];
    }
}