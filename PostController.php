<?php
namespace frontend\controllers;

use common\models\Comments;
use common\models\PostForm;
use common\models\Posts;
use common\models\Profiles;
use Yii;
use yii\data\ArrayDataProvider;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\Response;


/**
 * Site controller
 */
class PostController extends Controller
{
    public function actions()
    {
        return [
            'fast-post-upload' => [
                'class' => '\trntv\filekit\actions\UploadAction',
                'deleteRoute' => 'upload-delete'
            ],
            'upload-delete' => [
                'class' => '\trntv\filekit\actions\DeleteAction',
            ],
        ];
    }

    public function behaviors()
    {
        return [
            'rateLimiter' => [
                'class' => \yii\filters\RateLimiter::className(),
                'only' => ['update', 'create', 'create-fast-post', 'comment'],
                'enableRateLimitHeaders' => false,
                'errorMessage' => Yii::t('app', 'Request limit exceeded'),
            ],
            'access' => [
                'class' => AccessControl::className(),
                'rules' => [
                    [
                        'actions' => [
                            'update',
                            'create',
                            'create-fast-post',
                            'fast-post-upload',
                            'upload-delete',
                            'comment',
                            'delete'
                        ],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                    [
                        'actions' => ['tile','load', 'view'],
                        'allow' => true,
                    ]
                ],
            ],
        ];
    }

    public function actionUpdate($postId)
    {
        $post = Posts::findOne($postId);
        if (!$post) {
            return json_encode([
                'message' => Yii::t('app', 'Post not found'),
                'errors' => true
            ]);
        }
        $model = new PostForm([
            'header' => $post->header,
            'header_tags' => $post->header_tags,
            'postId' => $post->id,
            'image' => $post->image,
            'content' => $post->content,
            'wall' => $post->wall_id
        ]);



        if ($model->load(Yii::$app->request->post())) {
            if ($model->validate()) {
                $post->header = $model->header;
                $post->image = $model->image;
                $post->content = $model->content;
                $post->header_tags = $model->header_tags;

                // изменили стену, проверим может ли пользователь создавать посты на новой стене
                if ($model->wall != $post->wall_id){
                    $profile_wall = Profiles::findOne($model->wall);
                    if (!isset($profile_wall) ||
                        !Yii::$app->user->can('createPost', ['group'=>$profile_wall->name, 'profile_id'=>$profile_wall->id])){
                        return json_encode(false);
                    }
                }
                $post->wall_id = $model->wall;

                if (!Yii::$app->user->can('updatePost', ['group'=>$post->wall->name, 'post_author'=>$post->author_id])){
                    return json_encode([
                        'message' => Yii::t('app', 'You can not edit this post'),
                        'errors' => true
                    ]);
                }

                $post->save();
                return json_encode([
                    'html' => $this->renderAjax('post_tile', ['model' => $post]),
                    'postId' => $post->id,
                    'message' => Yii::t('app', 'Post saved'),
                    'updated' => true
                ]);
            } else {
                return json_encode([
                    'message' => $model->getFirstErrors(),
                    'errors' => true
                ]);
            }

        } else {
            return $this->renderAjax('forms/full_post', ['model' => $model ]);
        }
    }

    public function actionCreate()
    {
        $request = Yii::$app->request;

        $image = $request->post('image');
        $wall_name = $request->post('wall_name');
        $txt = $request->post('txt');
        $header_tags = $request->post('header_tags');

        $model = new PostForm();
        if ($image) {
            $model->image = $image;
        }
        if ($wall_name) {
            $wall = Profiles::findOne(['name'=>$wall_name]);
            if (isset($wall))  $model->wall = $wall->id;

        }
        if ($txt) {
            $model->content = $txt;
        }
        if ($header_tags) {
            $model->header_tags = $header_tags;
        }

        if ($model->load(Yii::$app->request->post())) {
            $profile_wall = Profiles::findOne($model->wall);
            if (!isset($profile_wall) ||
                !Yii::$app->user->can('createPost', ['group'=>$profile_wall->name, 'profile_id'=>$profile_wall->id])){
                return json_encode(false);
            }
            $model->validate();
            $post = $model->post();
            if ($model->hasErrors()) {
                return json_encode([
                    'message' => $model->getFirstErrors(),
                    'errors' => true
                ]);
            } else {
                $post = Posts::findOne($post->id);
                Profiles::communityBonus(Yii::$app->user->getId(), $post->wall_id, 'post', true, $post->id);
                return json_encode([
                    'html' => $this->renderAjax('post_tile', ['model' => $post]),
                    'postId' => $post->id,
                    'message' => Yii::t('app', 'Post added')
                ]);
            }
        } else {
            return $this->renderAjax('forms/full_post', ['model' => $model]);
        }
    }

    public function actionDelete()
    {
        $id = Yii::$app->request->post('post_id');
        $postModel = Posts::findOne($id);
        if (isset($postModel) && Yii::$app->user->can('createPost', ['group'=>$postModel->wall->name, 'profile_id'=>$postModel->wall->id])){
            Profiles::communityBonus($postModel->author_id, $postModel->wall_id, 'post', false, $postModel->id);
            $postModel->delete();
            return json_encode(true);
        }
        return json_encode(false);

    }

    public function actionCreateFastPost()
    {
        $model = new PostForm();
        if ($model->load(Yii::$app->request->post())) {
            $profile_wall = Profiles::findOne($model->wall);
            if (!isset($profile_wall) ||
                !Yii::$app->user->can('createPost', ['group'=>$profile_wall->name, 'profile_id'=>$profile_wall->id])){
                return json_encode(false);
            }

            if (!$model->validate()) {
                return json_encode([
                    'status' => 'error',
                    'message' => $model->getFirstError('content')
                ]);
            } else {
                $post = $model->post();
                if ($post) {
                    $post = Posts::findOne($post->id);
                    Profiles::communityBonus(Yii::$app->user->getId(), $post->wall_id, 'post', true, $post->id);
                    return json_encode([
                        'status' => 'ok',
                        'html' => $this->renderPartial('post_tile', ['model' => $post, 'item_class'=>'createpost'])
                    ]);
                } else {
                    return json_encode([
                        'status' => 'error',
                        'message' => Yii::t('app', 'Error')
                    ]);
                }
            }
        }
    }

    public function actionView($id, $commentId)
    {
        $model = Posts::findOne($id);
        if (!empty($model) && !$model->is_hidden && !$model->wall->is_hidden && Yii::$app->user->can('viewGroup', ['group'=>$model->wall->name])) {
            $comment = $model->getComments()->where(['id'=>$commentId])->one();
            return $this->renderPartial('view',['model' => $model, 'comment'=> $comment]);
        } else {
            return $this->renderPartial('not_found');
        }
    }

    public function actionComment($id)
    {
        $model = new Comments();
        if ($model->load(Yii::$app->request->post())&&$model->validate()) {
            if (!Yii::$app->user->can('viewGroup', ['group'=>$model->post->wall->name])){
                $json['errors'] = Yii::t('app', 'Error - not permitted');
            } else {
                $model->save();
                $profile = Posts::findOne($id);
                Profiles::communityBonus(Yii::$app->user->getId(), $profile->wall_id, 'comment', true, $model->id);
                $json['html'] = $this->renderPartial('/comment/view', [
                    'comment' => Comments::findOne($model->id)
                ]);
            }
        } else {
            $json['errors'] = $model['errors'];
        }
        Yii::$app->response->format = Response::FORMAT_JSON;
        return $json;
    }

    public function actionTile($id)
    {
        $model = Posts::findOne($id);
        return $this->renderAjax('post_tile', ['model' => $model]);
    }

    public function actionLoad($wall = null, $offset)
    {
        $posts = Posts::getPagePosts($wall, $offset)->all();
        return json_encode([
            'posts' => $this->renderPartial('index', [
                'posts' => $posts,
            ]),
            'feed_offset' => count($posts),
        ]);
    }
}
