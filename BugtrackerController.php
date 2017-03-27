<?php

namespace frontend\controllers;

use common\models\BugtrackerComment;
use common\models\BugtrackerSession;
use common\models\BugtrackerProduct;
use common\models\Notifications;
use common\models\Profiles;
use common\models\search\TaskSearch;

use yii\db\Query;
use yii\db\Expression;
use Yii;
use yii\data\ActiveDataProvider;
use yii\filters\AccessControl;
use yii\web\Controller;
use common\models\Task;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

class BugtrackerController extends Controller
{

    public function actions()
    {
        return [
            'task-file-upload' => [
                'class' => '\trntv\filekit\actions\UploadAction',
                'disableCsrf' => false,
                'deleteRoute' => 'task-file-delete'
            ],
            'task-file-delete' => [
                'class' => '\trntv\filekit\actions\DeleteAction',
            ],
            'product-image-upload' => [
                'class' => '\trntv\filekit\actions\UploadAction',
                'disableCsrf' => false,
                'deleteRoute' => 'product-image-delete'
            ],
            'product-image-delete' => [
                'class' => '\trntv\filekit\actions\DeleteAction',
            ],
        ];
    }

    public function behaviors()
    {
        return [
            'rateLimiter' => [
                'class' => \yii\filters\RateLimiter::className(),
                'only' => ['delete-task', 'edit-task', 'create-task', 'create-comment', 'edit-comment', 'delete-comment'],
                'enableRateLimitHeaders' => false,
                'errorMessage' => Yii::t('app', 'Request limit exceeded'),
            ],
            'Register_access' => [
                'class' => AccessControl::className(),
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
                'denyCallback' => function ($rule, $action) {
                    Yii::$app->session->setFlash('error', Yii::t('app', 'This page is for registered users only!'));
                    return $this->goHome();
                }
            ],
            'Admin_access' => [
                'class' => AccessControl::className(),
                'only' => ['notice-delete', 'notice-update', 'change-status-task', 'create-session', 'edit-session', 'delete-session', 'create-product', 'edit-product', 'delete-product', 'all-tasks'],
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['globaladmin'],
                    ],
                ],
                'denyCallback' => function ($rule, $action) {
                    Yii::$app->session->setFlash('error', Yii::t('app', 'You don\'t have permission'));
                    return $this->goHome();
                }
            ],
        ];
    }

    protected function queryRaiting($product_id = null)
    {
        $task_weight = Yii::$app->options->bugtracker_task_weight;
        $bug_minor_weight = Yii::$app->options->bugtracker_bug_minor_weight;
        $bug_medium_weight = Yii::$app->options->bugtracker_bug_medium_weight;
        $bug_serious_weight = Yii::$app->options->bugtracker_bug_serious_weight;
        $bug_critical_weight = Yii::$app->options->bugtracker_bug_critical_weight;

        $case_value = sprintf("CASE WHEN (bugtracker_task.type = %d) THEN %d
                                      WHEN (bugtracker_task.type = %d AND bugtracker_task.priority = %d) THEN %d
                                      WHEN (bugtracker_task.type = %d AND bugtracker_task.priority = %d) THEN %d
                                      WHEN (bugtracker_task.type = %d AND bugtracker_task.priority = %d) THEN %d
                                      WHEN (bugtracker_task.type = %d AND bugtracker_task.priority = %d) THEN %d
                                      ELSE 0 END", Task::TYPE_TASK, $task_weight, Task::TYPE_BUG, Task::PRIORITY_MINOR, $bug_minor_weight, Task::TYPE_BUG, Task::PRIORITY_MEDIUM, $bug_medium_weight,
                                    Task::TYPE_BUG, Task::PRIORITY_SERIOUS, $bug_serious_weight, Task::TYPE_BUG, Task::PRIORITY_CRITICAL, $bug_critical_weight
        );

        $allTasksQuery = (new \yii\db\Query)
            ->select(['profiles.id', 'count(*) as tasks_all'])
            ->from('profiles')
            ->innerJoin('bugtracker_task', 'bugtracker_task.user_id = profiles.id')
            ->innerJoin('bugtracker_session', 'bugtracker_session.id = bugtracker_task.session_id')
            ->innerJoin('bugtracker_product', 'bugtracker_product.id = bugtracker_session.product_id')
            ->where(['profiles.type'=>Profiles::TYPE_USER, 'profiles.status'=>Profiles::STATUS_ACTIVE, 'bugtracker_product.status' => BugtrackerProduct::STATUS_ACTIVE])
            ->andFilterWhere(['bugtracker_product.id' => $product_id])
            ->groupBy(['profiles.id']);

        $acceptTasksQuery = (new \yii\db\Query)
            ->select(['profiles.id', 'count(*) as tasks_accept'])
            ->addSelect([
                'weight' => "SUM($case_value)"
            ])
            ->from('profiles')
            ->leftJoin('bugtracker_task', 'bugtracker_task.user_id = profiles.id')
            ->innerJoin('bugtracker_session', 'bugtracker_session.id = bugtracker_task.session_id')
            ->innerJoin('bugtracker_product', 'bugtracker_product.id = bugtracker_session.product_id')
            ->where(['profiles.type'=>Profiles::TYPE_USER, 'profiles.status'=>Profiles::STATUS_ACTIVE])
            ->andWhere(['bugtracker_task.status' => [Task::STATUS_ACCEPTED, Task::STATUS_FIXED], 'bugtracker_product.status' => BugtrackerProduct::STATUS_ACTIVE])
            ->andFilterWhere(['bugtracker_product.id' => $product_id])
            ->groupBy(['profiles.id']);

        $fixedTasksQuery = (new \yii\db\Query)
            ->select(['profiles.id', 'count(*) as tasks_fixed'])
            ->from('profiles')
            ->leftJoin('bugtracker_task', 'bugtracker_task.user_id = profiles.id')
            ->innerJoin('bugtracker_session', 'bugtracker_session.id = bugtracker_task.session_id')
            ->innerJoin('bugtracker_product', 'bugtracker_product.id = bugtracker_session.product_id')
            ->where(['profiles.type'=>Profiles::TYPE_USER, 'profiles.status'=>Profiles::STATUS_ACTIVE])
            ->andWhere(['bugtracker_task.status' => [Task::STATUS_FIXED], 'bugtracker_product.status' => BugtrackerProduct::STATUS_ACTIVE])
            ->andFilterWhere(['bugtracker_product.id' => $product_id])
            ->groupBy(['profiles.id']);

        return  (new \yii\db\Query)
            ->select(['profiles.id', 'profiles.name', 'profiles.avatar', 'a.tasks_all', 'IFNULL(b.tasks_accept, 0) as tasks_accept' , 'IFNULL(c.tasks_fixed, 0) as tasks_fixed', 'b.weight'])
            ->from('profiles')
            ->innerJoin(['a' => $allTasksQuery], 'a.id = profiles.id')
            ->leftJoin(['b' => $acceptTasksQuery], 'b.id = profiles.id')
            ->leftJoin(['c' => $fixedTasksQuery], 'c.id = profiles.id')
            ->where(['profiles.type'=>Profiles::TYPE_USER, 'profiles.status'=>Profiles::STATUS_ACTIVE])
            ->andWhere(['>', 'b.tasks_accept',  0])
            ->orderBy(['b.weight'=>SORT_DESC, 'a.tasks_all'=>SORT_DESC, 'tasks_accept'=>SORT_DESC, 'tasks_fixed'=>SORT_DESC]);
    }

    public function actionIndex()
    {
        $this->layout = 'main';

        $user_groups =  \yii\helpers\ArrayHelper::getColumn(Yii::$app->user->identity->groups,'id');
        $user_groups [] = Yii::$app->user->getId();

        if (Yii::$app->user->can('globaladmin')) {
            $dataProvider = new ActiveDataProvider([
                'query' => BugtrackerProduct::find()
                    ->where(['status'=>BugtrackerProduct::STATUS_ACTIVE])
                    ->orderBy(['created_at'=>SORT_DESC]),
                'pagination' => false,
            ]);
        } else {
            $dataProvider = new ActiveDataProvider([
                'query' => BugtrackerProduct::find()
                    ->joinWith('visibleProfiles')
                    ->where(['or', ['=', 'bugtracker_product.hidden', '0'], ['in', 'profiles.id', $user_groups]])
                    ->andWhere(['bugtracker_product.status'=>BugtrackerProduct::STATUS_ACTIVE])
                    ->orderBy(['created_at'=>SORT_DESC]),
                'pagination' => false,
            ]);
        }

        $usersProvider = new ActiveDataProvider([
            'query'=> $this->queryRaiting()->limit(10),
            'pagination' => false,
        ]);

        $raiting =  $this->queryRaiting()->all();

        $rank = '-';
        foreach ($raiting as $num => $raiting_raw) {
            if ($raiting_raw['id'] == Yii::$app->user->getId()) {
                $rank = $num + 1;
                break;
            }

        }

        return $this->render('index', ['dataProvider' => $dataProvider,
            'usersProvider'=>$usersProvider,
            'rank'=>$rank,
            'openTasks' => Task::find()
                    ->joinWith('product')
                    ->where(['bugtracker_task.user_id' => Yii::$app->user->getId(), 'bugtracker_product.status' => BugtrackerProduct::STATUS_ACTIVE])
                    ->count(),
            'acceptedTasks' => Task::find()
                    ->joinWith('product')
                    ->where(['bugtracker_task.user_id' => Yii::$app->user->getId(), 'bugtracker_task.status' => [Task::STATUS_ACCEPTED, Task::STATUS_FIXED], 'bugtracker_product.status' => BugtrackerProduct::STATUS_ACTIVE])
                    ->count(),
        ]);
    }

    public  function actionAllTasks()
    {
        $this->layout = 'main_noresponsive';

        $searchModel = new TaskSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('all_tasks', ['dataProvider' => $dataProvider, 'searchModel' => $searchModel]);
    }

    public function actionTop()
    {
        $this->layout = 'main_noresponsive';

        $usersProvider = new ActiveDataProvider([
            'query'=> $this->queryRaiting(),
        ]);

        return $this->render('top', ['usersProvider'=>$usersProvider]);
    }

    public function actionCreateProduct()
    {
        $model = new BugtrackerProduct();
        $model->profiles = [];
        $model->contactPerson = Yii::$app->user->identity->name;
        $model->contact_email = Yii::$app->user->identity->email;
        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            foreach ($model->profiles as  $profile_id) {
                $profile_model = Profiles::findOne($profile_id);
                if ($profile_model) {
                    $model->link('visibleProfiles', $profile_model);
                }
            }
            //$model->unlinkAll('links', true);
            return json_encode([
                'status' => 'ok',
                'message' => Yii::t('app', 'Product added')
            ]);
        } else {
            $profiles_buff = [];
            foreach ($model->profiles as $profile_id) {
                $profile_model = Profiles::findOne($profile_id);
                if ($profile_model) {
                    $profiles_buff []=$profile_model;
                }
            }
            return json_encode([
                'status' => 'code',
                'html'  => $this->renderAjax('product/_product', ['model' => $model,  'profiles'=>$profiles_buff?$profiles_buff:$model->visibleProfiles]),
            ]);
        }
    }

    public function actionEditProduct($id)
    {
        $model = $this->findProduct($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            $model->unlinkAll('visibleProfiles', true);
            foreach ($model->profiles as  $profile_id) {
                $profile_model = Profiles::findOne($profile_id);
                if ($profile_model) {
                    $model->link('visibleProfiles', $profile_model);
                }
            }
            return json_encode([
                'status' => 'ok',
                'message' => Yii::t('app', 'Product updated')
            ]);
        } else {
            $profiles_buff = [];
            $model->profiles = ($model->profiles)?$model->profiles:[];
            $model->contactPerson = ($model->author)?$model->author->name:Yii::$app->user->identity->name;
            foreach ($model->profiles as  $profile_id) {
                $profile_model = Profiles::findOne($profile_id);
                if ($profile_model) {
                    $profiles_buff []=$profile_model;
                }
            }
            return json_encode([
                'status' => 'code',
                'html' => $this->renderAjax('product/_product', ['model' => $model, 'profiles'=>$profiles_buff?$profiles_buff:$model->visibleProfiles]),
            ]);
        }
    }

    public function actionDeleteProduct($id)
    {
        $model = $this->findProduct($id);

        $model->status = BugtrackerProduct::STATUS_INACTIVE;

        $model->save(false);

        Yii::$app->session->setFlash('success', Yii::t('app', 'Product removed'));

        return $this->redirect('/bugtracker');
    }

    public function actionProduct($id)
    {
        $this->layout = 'main_noresponsive';

        if (!(Yii::$app->user->can('viewProduct', ['product_id'=>$id])) ) {
            throw new ForbiddenHttpException('Access denied');
        }

        $product = $this->findProduct($id);

        $user_groups =  \yii\helpers\ArrayHelper::getColumn(Yii::$app->user->identity->groups,'id');
        $user_groups [] = Yii::$app->user->getId();

        if (Yii::$app->user->can('globaladmin')) {
            $dataProvider = new ActiveDataProvider([
                'query' => $product->getSessions()
                    ->orderBy(['updated_at'=>SORT_DESC]),
                'sort' => false,
                'pagination' => false,
            ]);
        } else {
            $dataProvider = new ActiveDataProvider([
                'query' => $product->getSessions()
                    ->joinWith('visibleProfiles')
                    ->where(['or', ['=', 'bugtracker_session.hidden', '0'], ['in', 'profiles.id', $user_groups]])
                    ->orderBy(['updated_at'=>SORT_DESC]),
                'sort' => false,
                'pagination' => [
                    'pageSize' => 5,
                ],
            ]);
        }

        /*
        $usersProvider = new ActiveDataProvider([
            'query'=> (new Query())->select(['profiles.id', 'profiles.name', 'profiles.avatar', 'count(*) as tasks_count'])
                ->from('profiles')
                ->innerJoin('bugtracker_task', 'bugtracker_task.user_id = profiles.id')
                ->innerJoin('bugtracker_session', 'bugtracker_task.session_id = bugtracker_session.id')
                ->where(['profiles.type'=>Profiles::TYPE_USER, 'profiles.status'=>Profiles::STATUS_ACTIVE, 'bugtracker_session.product_id'=>$product->id])
                ->groupBy(['profiles.id', 'profiles.name', 'profiles.avatar'])
                ->orderBy(['tasks_count'=>SORT_DESC])
                ->limit(10),
        ]);
        */

        $usersProvider = new ActiveDataProvider([
            'query'=> $this->queryRaiting($product->id)->limit(10),
            'pagination' => false,
        ]);

        $user_groups =  \yii\helpers\ArrayHelper::getColumn(Yii::$app->user->identity->groups,'id');
        $user_groups [] = Yii::$app->user->getId();
        $sessionsCount = (Yii::$app->user->can('globaladmin'))?
            $product->getSessions()->where(['bugtracker_session.status' => BugtrackerSession::STATUS_ACTIVE])->count():
            $product->getSessions()->joinWith('visibleProfiles')
                ->where(['or', ['=', 'bugtracker_session.hidden', '0'], ['in', 'profiles.id', $user_groups]])
                ->andWhere(['bugtracker_session.status' => BugtrackerSession::STATUS_ACTIVE])->count();

        return $this->render('product/index', [
            'dataProvider' => $dataProvider,
            'model' => $product,
            'usersProvider' => $usersProvider,
            'createTaskDisabled' => $sessionsCount == 0 ? "disabled" : ""
        ]);
    }

    public function actionSession($id)
    {
        $this->layout = 'main_noresponsive';

        if (!(Yii::$app->user->can('viewSession', ['session_id'=>$id])) ) {
            throw new ForbiddenHttpException('Access denied');
        }

        $searchModel = new TaskSearch();
        $session_model = $this->findSession($id);
        $searchModel->session_id = $session_model->id;
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('session/index', ['dataProvider' => $dataProvider, 'searchModel' => $searchModel, 'session'=>$session_model]);
    }

    public function actionNoticeDelete($id)
    {
        $model = $this->findProduct($id);

        $model->notice = null;
        $model->notice_date = null;
        $model->save();

        return json_encode([
            'status' => 'ok',
            'message'  =>  Yii::t('app', 'Notice removed')
        ]);
    }

    public function actionNoticeUpdate($id)
    {
        $model = $this->findProduct($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return json_encode([
                'status' => 'ok',
                'message' => Yii::t('app', 'Notice updated')
            ]);
        } else {
            return json_encode([
                'status' => 'code',
                'html'  => $this->renderAjax('product/notice', ['model' => $model]),
            ]);
        }

    }

    public function actionCreateTask()
    {

        $model = new Task();

        $session_id = Yii::$app->request->post('session_id');
        $session_model = BugtrackerSession::findOne($session_id);

        $product_id = Yii::$app->request->post('product_id');
        $product_model = BugtrackerProduct::findOne($product_id);

        if ( (empty($product_model) || !(Yii::$app->user->can('viewProduct', ['product_id'=>$product_id])) || $product_model->status == BugtrackerProduct::STATUS_INACTIVE ) &&
            (empty($session_model) || !(Yii::$app->user->can('viewSession', ['session_id'=>$session_id])) || $session_model->status == BugtrackerSession::STATUS_INACTIVE ) ){
            throw new ForbiddenHttpException('wrong parameters');
        }

        if ($product_model) {
            $user_groups =  \yii\helpers\ArrayHelper::getColumn(Yii::$app->user->identity->groups,'id');
            $user_groups [] = Yii::$app->user->getId();
            $sessions = (Yii::$app->user->can('globaladmin'))?
                $product_model->getSessions()->where(['bugtracker_session.status' => BugtrackerSession::STATUS_ACTIVE])->all():
                $product_model->getSessions()->joinWith('visibleProfiles')->where(['or', ['=', 'bugtracker_session.hidden', '0'], ['in', 'profiles.id', $user_groups]])
                ->andWhere(['bugtracker_session.status' => BugtrackerSession::STATUS_ACTIVE])->all();

            if (count($sessions)>0) {
                return json_encode([
                    'status' => 'code',
                    'html'  => $this->renderAjax('task/_task', ['model' => $model, 'sessions'=>$sessions, 'product'=>$product_model]),
                ]);
            } else {
                return json_encode([
                    'status' => 'error',
                    'message'  => Yii::t('app', 'Not found active sessions'),
                ]);
            }
        }

        $model->session_id = $session_model->id;
        if ($model->load(Yii::$app->request->post())  && $model->save()) {
            return json_encode([
                'status' => 'ok',
                'message' => Yii::t('app', 'Task added')
            ]);
        } else {
            return json_encode([
                'status' => 'code',
                'html'  => $this->renderAjax('task/_task', ['model' => $model, 'session'=>$session_model, 'product'=>$session_model->product]),
            ]);
        }
    }

    public function actionViewTask()
    {
        $id = Yii::$app->request->post('id');
        $task_model = $this->findTask($id);

        if (!(Yii::$app->user->can('viewSession', ['session_id'=>$task_model->session->id])) ) {
            throw new ForbiddenHttpException('Access denied');
        }

        $commentsProvider = new ActiveDataProvider([
            'query' => BugtrackerComment::find()->where(['task_id'=>$task_model->id])->orderBy(['id' => SORT_DESC])->limit(5),
            'pagination' => false,
        ]);

        $comment = new BugtrackerComment([
            'task_id' => $task_model->id
        ]);

        return json_encode([
            'status' => 'code',
            'html'  => $this->renderAjax('task/view', [
                'model' => $task_model,
                'commentsProvider' => $commentsProvider,
                'comment' => $comment,
                'count' => $commentsProvider->getCount(),
                'total_count' => BugtrackerComment::find()->where(['task_id'=>$task_model->id])->count()
            ]),
        ]);

    }

    public function actionDeleteTask($id)
    {
        $model = $this->findTask($id);

        if (!(Yii::$app->user->can('globaladmin') || ($model->author->id == Yii::$app->user->getId()) && $model->status == Task::STATUS_NEW) ) {
            return json_encode([
                'status' => 'error',
            ]);
        }
        $model->delete();


        return json_encode([
            'status' => 'ok',
            'message'  =>  Yii::t('app', 'Task removed')
        ]);
    }

    public function actionEditTask($id)
    {

        $model = $this->findTask($id);

        if (!(Yii::$app->user->can('globaladmin') || ($model->author->id == Yii::$app->user->getId()) && $model->status == Task::STATUS_NEW) ) {
            return json_encode([
                'status' => 'error',
            ]);
        }

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return json_encode([
                'status' => 'ok',
                'message' => Yii::t('app', 'Task updated')
            ]);
        }

        return json_encode([
            'status' => 'code',
            'html'  => $this->renderAjax('task/_task', ['model' => $model, 'session'=>$model->session]),
        ]);
    }

    public function actionChangeStatusTask($id)
    {
        $model = $this->findTask($id);
        $status = Yii::$app->request->post('status');
        $model->status = $status;
        $model->save();
        return json_encode([
            'status' => 'ok',
            'message' => Yii::t('app', 'Task updated')
        ]);

    }

    public function actionCreateSession()
    {
        $product_id = Yii::$app->request->post('product_id');
        $product_model = $this->findProduct($product_id);
        $model = new BugtrackerSession();
        $model->profiles = [];
        $model->start = date('d.m.Y');
        $model->end = date('d.m.Y', strtotime('+1 months', time()));
        $model->product_id = $product_model->id;
        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            foreach ($model->profiles as  $profile_id) {
                $profile_model = Profiles::findOne($profile_id);
                if ($profile_model) {
                    $model->link('visibleProfiles', $profile_model, ['product_id'=>$model->product->id]);
                }
            }
            return json_encode([
                'status' => 'ok',
                'message' => Yii::t('app', 'Session added')
            ]);
        } else {
            $profiles_buff = [];
            foreach ($model->profiles as $profile_id) {
                $profile_model = Profiles::findOne($profile_id);
                if ($profile_model) {
                    $profiles_buff []=$profile_model;
                }
            }
            return json_encode([
                'status' => 'code',
                'html'  => $this->renderAjax('session/_session', ['model' => $model, 'product'=>$product_model, 'profiles'=>$profiles_buff?$profiles_buff:$model->visibleProfiles]),
            ]);
        }

    }

    public function actionEditSession($id)
    {

        $model = $this->findSession($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            $model->unlinkAll('visibleProfiles', true);
            foreach ($model->profiles as  $profile_id) {
                $profile_model = Profiles::findOne($profile_id);
                if ($profile_model) {
                    $model->link('visibleProfiles', $profile_model, ['product_id'=>$model->product->id]);
                }
            }
            return json_encode([
                'status' => 'ok',
                'message' => Yii::t('app', 'Session updated')
            ]);
        } else {
            $profiles_buff = [];
            $model->profiles = ($model->profiles)?$model->profiles:[];
            foreach ($model->profiles as  $profile_id) {
                $profile_model = Profiles::findOne($profile_id);
                if ($profile_model) {
                    $profiles_buff []=$profile_model;
                }
            }
            return json_encode([
                'status' => 'code',
                'html' => $this->renderAjax('session/_session', ['model' => $model, 'product'=>$model->product, 'profiles'=>$profiles_buff?$profiles_buff:$model->visibleProfiles]),
            ]);
        }
    }

    public function actionDeleteSession($id)
    {
        $model = $this->findSession($id);

        $model->delete();


        return json_encode([
            'status' => 'ok',
            'message'  =>  Yii::t('app', 'Session removed')
        ]);
    }

    public function actionStat()
    {
        $this->layout = 'main_noresponsive';

        $searchModel = new TaskSearch();
        $searchModel->author = Yii::$app->user->identity->name;
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('person_tasks', ['dataProvider' => $dataProvider, 'searchModel' => $searchModel]);
    }

    public function actionCreateComment()
    {

        $model = new BugtrackerComment();

        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
                if (!(Yii::$app->user->can('viewSession', ['session_id'=>$model->task->session->id])) ) {
                    throw new ForbiddenHttpException('Access denied');
                }
                if  ($model->save()) {

                    // post comment notification
                    if ($model->task->author->id != Yii::$app->user->getId()) {
                        (new Notifications([
                            'type' => Notifications::BUGTRACKER_COMMENT,
                            'to_user' => $model->task->author->id,
                            'resource_type' => 'task',
                            'resource_id' => $model->task->id,
                            'is_new' => 1,
                            'from_user' => Yii::$app->user->getId(),
                            'params' => json_encode(['text'=>\common\helpers\PostText::truncate($model->content)])
                        ]))->save();
                    }

                    return json_encode([
                        'status' => 'ok',
                        'html'  => $this->renderAjax('task/comment/_insert_comment', ['comment' => $this->findComment($model->id)]),
                        'message'  =>  Yii::t('app', 'Comment added')
                    ]);
                }
        }
        return json_encode([
            'status' => 'error',
            'message' => $model->getFirstError('content')
        ]);
    }

    public function actionDeleteComment($id)
    {
        $model = $this->findComment($id);

        if ( (!(Yii::$app->user->can('editBtComment', ['comment_author'=>$model->user->id])) ) ){
            throw new ForbiddenHttpException('Access denied');
        }

        $model->delete();


        return json_encode([
            'status' => 'ok',
            'message'  =>  Yii::t('app', 'Comment removed')
        ]);
    }

    public function actionEditComment($id)
    {
        $model = $this->findComment($id);

        if ( (!(Yii::$app->user->can('editBtComment', ['comment_author'=>$model->user->id])) ) ){
            throw new ForbiddenHttpException('Access denied');
        }

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return json_encode([
                'status' => 'ok',
                'html'  => $this->renderAjax('task/comment/_insert_comment', ['comment' => $model]),
                'message' => Yii::t('app', 'Comment updated')
            ]);
        }

        if ($model->hasErrors()) {
            return json_encode([
                'status' => 'error',
                'message'  => $model->getFirstError('content'),
            ]);
        }

        return json_encode([
            'status' => 'code',
            'html'  => $this->renderAjax('task/comment/_comment_form', ['comment' => $model]),
        ]);
    }

    public function actionLoadComments($taskId, $offset)
    {
        $task_model = $this->findTask($taskId);

        if (!(Yii::$app->user->can('viewSession', ['session_id'=>$task_model->session->id])) ) {
            throw new ForbiddenHttpException('Access denied');
        }

        $commentsProvider = new ActiveDataProvider([
            'query' => BugtrackerComment::find()->where(['task_id'=>$task_model->id])->orderBy(['id' => SORT_DESC])->offset($offset)->limit(10),
            'pagination' => false,
        ]);


        return json_encode([
            'status' => 'ok',
            'html' => $this->renderAjax('task/comment/_list',['commentsProvider' => $commentsProvider])
        ],JSON_FORCE_OBJECT);
    }

    protected function findComment($id)
    {
        if (($model = BugtrackerComment::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }

    protected function findTask($id)
    {
        if (($model = Task::find()->joinWith('product')->where(['bugtracker_task.id'=>$id, 'bugtracker_product.status'=>BugtrackerProduct::STATUS_ACTIVE])->one()) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }

    protected function findSession($id)
    {
        if (($model = BugtrackerSession::find()->joinWith('product')->where(['bugtracker_session.id'=>$id, 'bugtracker_product.status'=>BugtrackerProduct::STATUS_ACTIVE])->one()) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }


    protected function findProduct($id)
    {
        if (($model = BugtrackerProduct::findOne(['id'=>$id, 'status'=>BugtrackerProduct::STATUS_ACTIVE])) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }

}
