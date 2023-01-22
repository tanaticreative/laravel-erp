<?php


namespace Tan\ERP\Entities;

use Tan\ERP\Contracts\EntitySyncState;
use Tan\ERP\Exceptions\NotSupportedByAGRException;
use App\Models\Product;

/**
 * Article entity for Product
 *
 * @property string $name name of the product
 * @property string $articleNumber product slug
 *
 * @property Product $model
 */
class ProductArticle extends Article
{
    const ARTICLE_TYPE = Article::ARTICLE_TYPE_BASIC;

    /**
     * {@inheritdoc}
     */
    public function syncModel()
    {
        throw new NotSupportedByAGRException();
    }


    /**
     * {@inheritdoc}
     */
    public function createModel()
    {
        throw new NotSupportedByAGRException();
    }


    /**
     * {@inheritdoc}
     */
    public function fillFromModel(EntitySyncState $model)
    {
        if (!($model instanceof Product)) {
            throw new \InvalidArgumentException("Only instance of " . Product::class . ' is supported');
        }

        parent::fillFromModel($model);

        $unit = Unit::findByName($model->unit);
        // NOT SUPPORTED for now $category = ArticleCategory::findByGoodsCategory($model->category->goodsCategory);
        $category = ArticleCategory::findByName(ArticleCategory::CATEGORY_TENDER_NAME);

        if ($model->syncState && $model->syncState->entity_id) {
            $this->id = $model->syncState->entity_id;
        }

        $this->name = $model->name;
        $this->unitId = $unit->id;
        $this->unitName = $unit->name;
        $this->articleNumber = 'AGR-' . $model->id;
        $this->active = $model->enabled;
        $this->articleCategoryId = $category->id ?? null;
        $this->serviceArticle = false;
        $this->productionArticle = true;
        $this->applyCashDiscount = false;
        $this->longText = $model->description;
    }
}
