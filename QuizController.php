<?php
namespace frontend\controllers;

use frontend\models\search\QuizSearch;
use common\models\Profiles;
use common\models\Quiz;
use common\models\QuizQuestion;
use common\models\QuizUser;
use Yii;
use yii\data\ArrayDataProvider;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

/**
 * Site controller
 */
class QuizController extends Controller
{
    public $layout = 'womasonry';

    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'rules' => [
                    [
                        'actions' => ['index'],
                        'allow' => true,
                        'roles' => ['?'],
                    ],
                    [
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
        ];
    }

    public function actionIndex($actual = 1, $type = Quiz::TYPE_REAL)
    {
        if (Yii::$app->user->isGuest) {
            Yii::$app->session->setFlash('error', Yii::t('app', 'This page is for registered users only!'));
            return $this->goHome();
        }

        $quizSearch = new QuizSearch();
        $crit = [
            'type' => $type,
            'actual' => $actual
        ];
        $quizzes = $quizSearch->search($crit);
        return $this->render('index',['dataProvider' => $quizzes]);
    }

    public function actionStartDanger($quiz)
    {
        $quizModel = Quiz::findOne($quiz);
        if (!$quizModel) {
            throw new NotFoundHttpException(Yii::t('app','The requested page does not exist.'));
        }
        return $this->renderAjax('start-danger',['model' => $quizModel]);
    }

    public function actionStart($quiz)
    {
        $quizModel = Quiz::find()->where(['id'=>$quiz, 'status'=>Quiz::STATUS_PUBLISHED])->andWhere(['or', 'active_until = 0',  ['>=','active_until', time()]])->one();
        if (!$quizModel) {
            throw new NotFoundHttpException(Yii::t('app','The requested page does not exist.'));
        }
        if (!$quizModel->startQuiz()) {
            return $this->redirect(['quiz/index']);
        }
        return $this->render('view',['model' => $quizModel]);
    }

    public function actionView($quiz)
    {
        $quizModel = Quiz::findOne($quiz);
        $userModel = Profiles::findOne(Yii::$app->user->getId());
        $quModel = QuizUser::find()->where(['user_id'=>$userModel->id, 'quiz_id'=>$quizModel->id])->andWhere('finished_at is not NULL')->orderBy('mistakes ASC')->addOrderBy('(finished_at - started_at) ASC')->limit(1)->one();
        if ((!$quizModel)||(!$userModel)||(!$quModel)) {
            throw new NotFoundHttpException(Yii::t('app','The requested page does not exist.'));
        }
        if ($quizModel->isFinished()) {
            return $this->render('view-result',[
                'quiz' => $quizModel,
                'user' => $userModel,
                'quiz_user' => $quModel,
            ]);
        } else {
            throw new ForbiddenHttpException(Yii::t('app', 'View of this quiz prohibited'));
        }

    }

    public function actionResult()
    {
        $result = Yii::$app->request->post();
        $quiz = ($result)?Quiz::findOne($result['quizid']):false;
        if (!$quiz) {
            throw new NotFoundHttpException(Yii::t('app','The requested page does not exist.'));
        }
        if (!$quiz->saveResult($result)){
            return $this->redirect(['quiz/start', 'quiz' => $quiz->id]);
        }
        return $this->redirect(['quiz/rank', 'quiz' => $quiz->id, 'user' => Yii::$app->user->id]);
    }

    public function actionRank($quiz, $user = null)
    {
        $quizModel = Quiz::findOne($quiz);
        if (!$quizModel) {
            throw new NotFoundHttpException(Yii::t('app','The requested page does not exist.'));
        }

        $sql="  SELECT t.quiz_id, t.user_id, t3.name, t3.avatar, t.cnt, t2.`mistakes`,  (t2.`finished_at` - t2.`started_at`) time ".
             "   FROM (SELECT quiz_id, user_id, count(*) as cnt FROM quiz_user where quiz_id = :quiz_id and `finished_at` is not null group by quiz_id, user_id) t ".
             "   JOIN quiz_user t2 on t2.id = ( ".
             "        SELECT `id` ".
             "        FROM quiz_user ".
             "         WHERE quiz_id = :quiz_id and user_id = t.user_id and `finished_at` is not null ".
             "         ORDER BY `mistakes`, (`finished_at` - `started_at`) ASC ".
             "         LIMIT 1 ".
             "       ) ".
             "  JOIN profiles t3 on t3.id = t.user_id ".
             "         ORDER BY t2.`mistakes`, (t2.`finished_at` - t2.`started_at`), t.cnt ASC ";

        $quizUsers = Yii::$app->db->createCommand($sql, [':quiz_id' => $quizModel->id])->queryAll();

        $user_time =  $user_mistakes = $user_cnt = 0;
        foreach ($quizUsers as $num => &$queryUser) {
            $queryUser['num'] = $num + 1;
            if ($queryUser['user_id'] != Yii::$app->user->getId() && $num>9){
                unset($quizUsers[$num]);
            }
            if ($queryUser['user_id'] == Yii::$app->user->getId()) {
                $user_time = $queryUser['time'];
                $user_mistakes = $queryUser['mistakes'];
                $user_cnt = $queryUser['cnt'];
            }

        }

        return $this->render('rank', [
            'dataProvider' => new ArrayDataProvider([
                'allModels' => $quizUsers
            ]),
            'user' => $user?Profiles::findOne($user):null,
            'quiz' => $quizModel,
            'user_time' => $user_time,
            'user_mistakes' => $user_mistakes,
            'user_cnt' => $user_cnt,
        ]);
    }
}
