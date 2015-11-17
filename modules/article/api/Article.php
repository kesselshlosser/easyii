<?php
namespace yii\easyii\modules\article\api;

use Yii;

use yii\data\ActiveDataProvider;
use yii\easyii\models\Tag;
use yii\easyii\modules\article\ArticleModule;
use yii\easyii\modules\article\models\Category;
use yii\easyii\modules\article\models\Item;
use yii\easyii\widgets\Fancybox;
use yii\widgets\LinkPager;

/**
 * Article module API
 * @package yii\easyii\modules\article\api
 *
 * @method static CategoryObject cat(mixed $id_slug) Get article category by id or slug
 * @method static array tree() Get article categories as tree
 * @method static array cats() Get article categories as flat array
 * @method static array items(array $options = []) Get list of articles as ArticleObject objects
 * @method static ArticleObject get(mixed $id_slug) Get article object by id or slug
 * @method static mixed last(int $limit = 1) Get last articles
 * @method static void plugin() Applies FancyBox widget on photos called by box() function
 * @method static string pages() returns pagination html generated by yii\widgets\LinkPager widget.
 * @method static \stdClass pagination() returns yii\data\Pagination object.
 */

class Article extends \yii\easyii\components\API
{
    private $_cats;
    private $_adp;
    private $_item = [];

    public function api_cat($id_slug)
    {
        if(!isset($this->_cats[$id_slug])) {
            $this->_cats[$id_slug] = new CategoryObject(Category::get($id_slug));
        }
        return $this->_cats[$id_slug];
    }

    public function api_tree()
    {
        return Category::tree();
    }

    public function api_cats($options = [])
    {
        $result = [];
        foreach(Category::cats() as $model){
            $result[] = new CategoryObject($model);
        }
        if(!empty($options['tags'])){
            foreach($result as $i => $item){
                if(!in_array($options['tags'], $item->tags)){
                    unset($result[$i]);
                }
            }
        }

        return $result;
    }

    public function api_items($options = [])
    {
        $result = [];

        $with = ['seo', 'category'];
        if(ArticleModule::setting('enableTags')){
            $with[] = 'tags';
        }
        $query = Item::find()->with($with)->status(Item::STATUS_ON);

        if(!empty($options['where'])){
            $query->andFilterWhere($options['where']);
        }
        if(!empty($options['tags'])){
            $query
                ->innerJoinWith('tags', false)
                ->andWhere([Tag::tableName() . '.name' => (new Item())->filterTagValues($options['tags'])])
                ->addGroupBy('item_id');
        }
        if(!empty($options['orderBy'])){
            $query->orderBy($options['orderBy']);
        } else {
            $query->sortDate();
        }

        $this->_adp = new ActiveDataProvider([
            'query' => $query,
            'pagination' => !empty($options['pagination']) ? $options['pagination'] : []
        ]);

        foreach($this->_adp->models as $model){
            $result[] = new ArticleObject($model);
        }
        return $result;
    }

    public function api_last($limit = 1, $where = null)
    {
        $result = [];

        $with = ['seo'];
        if(ArticleModule::setting('enableTags')){
            $with[] = 'tags';
        }
        $query = Item::find()->with($with)->status(Item::STATUS_ON)->sortDate()->limit($limit);
        if($where){
            $query->andFilterWhere($where);
        }

        foreach($query->all() as $item){
            $result[] = new ArticleObject($item);
        }
        return $result;
    }

    public function api_get($id_slug)
    {
        if(!isset($this->_item[$id_slug])) {
            $this->_item[$id_slug] = $this->findItem($id_slug);
        }
        return $this->_item[$id_slug];
    }

    public function api_plugin($options = [])
    {
        Fancybox::widget([
            'selector' => '.easyii-box',
            'options' => $options
        ]);
    }

    public function api_pagination()
    {
        return $this->_adp ? $this->_adp->pagination : null;
    }

    public function api_pages()
    {
        return $this->_adp ? LinkPager::widget(['pagination' => $this->_adp->pagination]) : '';
    }

    private function findItem($id_slug)
    {
        $article = Item::find()->where(['or', 'item_id=:id_slug', 'slug=:id_slug'], [':id_slug' => $id_slug])->status(Item::STATUS_ON)->one();
        if($article) {
            $article->updateCounters(['views' => 1]);
            return new ArticleObject($article);
        } else {
            return null;
        }
    }
}