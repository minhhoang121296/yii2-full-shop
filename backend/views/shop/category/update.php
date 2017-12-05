<?php
/**
 * Created by PhpStorm.
 * User: volynets
 * Date: 28.08.17
 * Time: 12:18
 */


/* @var $this yii\web\View */
/* @var $category core\entities\Shop\Category\Category */
/* @var $model core\forms\manage\Shop\CategoryForm */

$this->title = 'Update Category: ' . $category->name;
$this->params['breadcrumbs'][] = ['label' => 'Categories', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $category->name, 'url' => ['view', 'id' => $category->id]];
$this->params['breadcrumbs'][] = 'Update';
?>
<div class="category-update">

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
