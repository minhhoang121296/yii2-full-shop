<?php
/**
 * Created by PhpStorm.
 * User: volynets
 * Date: 05.09.17
 * Time: 13:55
 */

namespace frontend\urls;

use core\entities\Shop\Category\Category;
use core\readModels\Shop\CategoryReadRepository;
use yii\caching\TagDependency;
use yii\base\InvalidParamException;
use yii\base\Object;
use yii\caching\Cache;
use yii\helpers\ArrayHelper;
use yii\web\UrlNormalizerRedirectException;
use yii\web\UrlRuleInterface;

/**
 * Класс обрабатывает пришедший гет запрос клиента, сравнивает его из слагами категорий в бд
 * склеивает последовательность родительских категорий если слаг равен категории детенышу
 * кеширует либо тянет из кеша результат и возвращает в систему понятный для нее формат
 * контроллер/метод?айдишник?параметры
 * Class CategoryUrlRule
 * @package frontend\urls
 */
class CategoryUrlRule extends Object implements UrlRuleInterface
{
    public $prefix = 'catalog';

    private $repository;
    private $cache;

    public function __construct(CategoryReadRepository $repository, Cache $cache, $config = [])
    {
        parent::__construct($config);
        $this->repository = $repository;
        $this->cache = $cache;
    }

    /**
     * @param \yii\web\UrlManager $manager
     * @param \yii\web\Request $request
     * @return array|bool
     * @throws UrlNormalizerRedirectException
     */
    public function parseRequest($manager, $request)
    {
        if (preg_match('#^' . $this->prefix . '/(.*[a-z])$#is', $request->pathInfo, $matches)) {
            $path = $matches['1'];

            $result = $this->cache->getOrSet(['category_route', 'path' => $path], function () use ($path) {
                if (!$category = $this->repository->findBySlug($this->getPathSlug($path))) {
                    return ['id' => null, 'path' => null];
                }
                return ['id' => $category->id, 'path' => $this->getCategoryPath($category)];
            }, null, new TagDependency(['tags' => ['categories']]));

            if (empty($result['id'])) {
                return false;
            }

            if ($path != $result['path']) {
                throw new UrlNormalizerRedirectException(['shop/catalog/category', 'id' => $result['id']], 301);
            }

            return ['shop/catalog/category', ['id' => $result['id']]];
        }
        return false;
    }

    /**
     * @param \yii\web\UrlManager $manager
     * @param string $route
     * @param array $params
     * @return bool|mixed|string
     */
    public function createUrl($manager, $route, $params)
    {
        if ($route == 'shop/catalog/category') {
            if (empty($params['id'])) {
                throw new InvalidParamException('Empty id.');
            }
            $id = $params['id'];

            $url = $this->cache->getOrSet(['category_route', 'id' => $id], function () use ($id) {
                if (!$category = $this->repository->find($id)) {
                    return null;
                }
                return $this->getCategoryPath($category);
            }, null, new TagDependency(['tags' => ['categories']]));

            if (!$url) {
                throw new InvalidParamException('Undefined id.');
            }

            $url = $this->prefix . '/' . $url;
            unset($params['id']);
            if (!empty($params) && ($query = http_build_query($params)) !== '') {
                $url .= '?' . $query;
            }

            return $url;
        }
        return false;
    }

    /**
     * @param $path
     * @return string
     */
    private function getPathSlug($path): string
    {
        $chunks = explode('/', $path);
        return end($chunks);
    }

    /**
     * @param Category $category
     * @return string
     */
    private function getCategoryPath(Category $category): string
    {
        $chunks = ArrayHelper::getColumn($category->getParents()->andWhere(['>', 'depth', 0])->all(), 'slug');
        $chunks[] = $category->slug;
        return implode('/', $chunks);
    }


}