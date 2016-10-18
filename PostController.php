<?php

namespace app\controllers;

use Yii;
use app\components\SiteComponent;
use app\models\Category;
use app\models\Post;
use yii\helpers\Url;
use app\models\ConsultForm;
use app\models\Review;
use yii\data\Pagination;

class PostController extends SiteComponent
{

    /**
     * Главная страница статьи
     * @param $categoryAlias
     * @param $postAlias
     * @return string
     */
     
    /*
    public function init()
    {
		$this->view->blocks['header'] = '@app/views/layouts/_1column.php';
    }
    */
    //$alone - не учитывать категорию
    public function actionIndex($categoryAlias, $postAlias, $alone = FALSE)
    {
        // Вытаскиваем категорию и статью
		//exit();
		$url = parse_url($_SERVER['REQUEST_URI']);
		$url = explode('/', $url['path']);
		array_pop($url);
		array_shift($url);

		$categoryAlias = implode('/', $url);

        $category = $this->getCategoryByAlias($categoryAlias);
        $post = Post::find()->where(['alias' => $postAlias, 'status' => 1])->one();

        $model = new ConsultForm;
          if (Yii::$app->request->post() && $model->load(Yii::$app->request->post())){              
            $model->contact($this->settings->email);
            Yii::$app->session->setFlash('consiltFormSubmitted','Спасибо ! Мы скоро свяжемся с вами !');
            
        }
        $query = Review::find()->where(['status' => 1,'post_id'=>$post->id])->orderBy(['created_at'=> SORT_DESC]);
       
        $pagination = new Pagination([
                'totalCount' => $query->count(),
                'route'      => Url::toRoute('review/list'),
                'pageSize'   => 5
            ]);
        $model      = $query->offset($pagination->offset)->limit($pagination->limit)->all();
        
        $model_new = new Review(['scenario' => 'default']);
        $model_new->post_id = $post->id;

        if ($model_new->load(Yii::$app->request->post())){
            
                $model_new->save();
                $model_new->contact($this->settings->email);
                //$model_new->contact('mzherikhova@mail.ru');
                Yii::$app->session->setFlash('reviewFormSubmitted');
                return $this->refresh();
            
        }
        
        if (empty($category) || empty($post) ||
			$category['id'] != $post['id_category']) throw new \yii\web\HttpException(404, 'Страница не найдена ');
        
        $this->setMetaTags($post->meta_title, $post->meta_keywords, $post->meta_description);
	//'post.tpl'

        return $this->render($post->template, [
            'post'     => $post,
            'category' => $category,
            'alone' => $alone,
            'model_new'=>$model_new,
            'model' =>$model,
            'pagination' => $pagination,
        ]);

    }

    /**
     * Получение данных о категории
     * @param $alias
     * @return array|null|\yii\db\ActiveRecord
     * @throws \yii\base\Exception
     */
    private function getCategoryByAlias($alias)
    {
        $category = Category::find()->where(['alias' => $alias, 'status' => 1])->one();
        if ($category) {
            return $category;
        }
    }
}
