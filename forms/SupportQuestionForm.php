<?php

namespace frontend\models\forms;

use common\models\BonusLog;
use common\models\SupportQuestion;
use Yii;

class SupportQuestionForm extends SupportQuestion
{
    public $title;
    public $content;
    public $tagsinput;
    public $custom_cost;

    const DEFAULT_COST = 5;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['title', 'content'], 'required'],
            [['custom_cost'], 'integer', 'min' => self::DEFAULT_COST, 'max' => 50],
            [['content', 'tagsinput'], 'string'],
            [['title'], 'string', 'max' => 255],
            ['custom_cost', function ($attribute, $params) {
                if ($this->$attribute > Yii::$app->user->identity->balance) {
                    $this->addError($attribute, Yii::t('app/support', 'Not enough points on the balance'));
                }
            }]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'title' => Yii::t('app/support','Question title'),
            'content' => Yii::t('app/support','Question text'),
            'tagsinput' => Yii::t('app','Tags'),
            'custom_cost' => Yii::t('app/support', 'Custom cost'),
        ];
    }

    public function create()
    {
        /** @var $user \common\models\Profiles */
        $user = Yii::$app->user->identity;
        $question = new SupportQuestion([
            'user_id' => $user->getId(),
            'title' => $this->title,
            'content' => $this->content,
            'custom_cost' => $this->custom_cost
        ]);
        if (!empty($this->tagsinput)) {
            $question->tags_list = is_string($this->tagsinput) ? explode(',', $this->tagsinput) : $this->tagsinput;
        }
        if ($question->save()) {
            $user->addBonus(-$this->custom_cost, BonusLog::TYPE_SUPPORT, 'Support quesion ID:'.$question->id, true, $question->id);
            return $question;
        }
        return null;
    }
}