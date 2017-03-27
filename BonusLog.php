<?php

namespace common\models;

use Yii;
use yii\behaviors\TimestampBehavior;

/**
 * This is the model class for table "bonus_log".
 *
 * @property integer $id
 * @property string $type
 * @property integer $amount
 * @property integer $balance
 * @property integer $user_id
 * @property string $created_at
 */
class BonusLog extends \yii\db\ActiveRecord
{
    const TYPE_BIRTHDAY = 'birthday';
    const TYPE_PRESENT = 'present';
    const TYPE_SHOP = 'shop';
    const TYPE_ADMIN = 'admin';
    const TYPE_EMAIL_CONFIRM = 'email confirm';
    const TYPE_REFERAL = 'Referal bonus';
    const TYPE_OLD_MEMBER = 'From english forum';
    const TYPE_POST = 'Post bonus';
    const TYPE_COMMENT = 'Comment bonus';
    const TYPE_SOCIAL = 'Social net bonus';
    const TYPE_BUGTRACKER = 'Bugtracker bonus';
    const TYPE_SUPPORT = 'Support bonus';

    public function behaviors()
    {
        return [
            'timestamp' => [
                'class' => TimestampBehavior::className(),
                'createdAtAttribute' => 'created_at',
                'updatedAtAttribute' => false,
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'bonus_log';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['amount','user_id','balance', 'param'], 'integer'],
            [['created_at'], 'safe'],
            [['type', 'description'], 'string', 'max' => 255],
            ['type', 'in', 'range' => [self::TYPE_BIRTHDAY, self::TYPE_PRESENT, self::TYPE_SHOP, self::TYPE_ADMIN, self::TYPE_EMAIL_CONFIRM, self::TYPE_REFERAL, self::TYPE_OLD_MEMBER, self::TYPE_POST, self::TYPE_COMMENT, self::TYPE_SOCIAL, self::TYPE_BUGTRACKER]],
        ];
    }

    public function afterSave($insert, $changedAttributes){
        parent::afterSave($insert, $changedAttributes);

        if ($this->type != self::TYPE_ADMIN) {
            $notification = new Notifications();
            $notification->type = ($this->type == self::TYPE_BUGTRACKER)?(Notifications::BONUS_BT):(Notifications::BONUS_CLUB);
            $notification->to_user = $this->user_id;
            $notification->resource_type = 'profile';
            $notification->resource_id = $this->user_id;
            $notification->is_new = 1;
            $notification->save();
        }

    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'type' => Yii::t('app', 'Type'),
            'amount' => Yii::t('app', 'Amount'),
            'balance' => Yii::t('app', 'Balance'),
            'description' => Yii::t('app', 'Description'),
            'created_at' => Yii::t('app', 'Created At'),
            'param' => Yii::t('app', 'Param'),
        ];
    }

    public static function getTypes()
    {
        return [
            self::TYPE_BIRTHDAY=>Yii::t('app',self::TYPE_BIRTHDAY),
            self::TYPE_PRESENT=>Yii::t('app',self::TYPE_PRESENT),
            self::TYPE_SHOP=>Yii::t('app',self::TYPE_SHOP),
            self::TYPE_ADMIN=>Yii::t('app',self::TYPE_ADMIN),
            self::TYPE_EMAIL_CONFIRM=>Yii::t('app',self::TYPE_EMAIL_CONFIRM),
            self::TYPE_REFERAL=>Yii::t('app',self::TYPE_REFERAL),
            self::TYPE_OLD_MEMBER=>Yii::t('app',self::TYPE_OLD_MEMBER),
            self::TYPE_POST=>Yii::t('app',self::TYPE_POST),
            self::TYPE_COMMENT=>Yii::t('app',self::TYPE_COMMENT),
            self::TYPE_SOCIAL=>Yii::t('app',self::TYPE_SOCIAL),
            self::TYPE_BUGTRACKER=>Yii::t('app',self::TYPE_BUGTRACKER),
        ];
    }

    public static function addLog($amount, $type, $description = '', $param = null)
    {
        Yii::$app->params['bonusLog'] = [
            'amount' => $amount,
            'type'  => $type,
            'description' => $description,
            'param' => $param
        ];
    }

    public static function haveBonus($type, $userID, $description = null)
    {
        $query = BonusLog::find()->where(['type'=>$type, 'user_id'=>$userID]);
        if ($description){
            $query->andWhere(['description'=>$description]);
        }
        return $query->count();
    }

}
