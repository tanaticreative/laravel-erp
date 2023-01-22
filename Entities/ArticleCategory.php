<?php


namespace Tan\ERP\Entities;

use Tan\ERP\Contracts\EntityEvent;
use Tan\ERP\Contracts\EntitySyncState;
use Tan\ERP\Entities\FakeArticle\Service;
use Tan\ERP\Exceptions\NotSupportedByAGRException;
use Tan\ERP\Support\Facade;
use App\Models\Category;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

/**
 * Article Category ERP Entity  (Merchandise Categories)
 *
 * @property string $name
 * @property int|null $parentCategoryId
 */
class ArticleCategory extends BaseEntity
{
    const ENTITY_NAME = 'articleCategory';

    // for tender products
    const CATEGORY_TENDER_NAME = 'Ausschreibungen';
    const CATEGORY_SERVICE_NAME = 'Service - Gebührenartikel für Serviceleistungen';
    const CATEGORY_FEE_NAME = 'Gebühren';

    protected $casts = [
        'parentCategoryId' => 'integer',
    ];


    /**
     * @param Category $category
     * @return string
     */
    protected static function getNameFromGoods(Category $category)
    {
        // TODO: how to force default ERP language for category?
        return 'Ausschreibungen - ' . $category->name;
    }


    /**
     * {@inheritdoc}
     */
    public function save(array $options = [])
    {
        $erpEntity = parent::find($this->id);
        if ($erpEntity) {
            // ERP wants all data for entity so we give it
            $this->fill(array_merge($erpEntity->toArray(), $this->toArray()));
            $data = Facade::getClient()->update($this);
            $this->fill($data);
            $event = 'updated';
        } else {
            $data = Facade::getClient()->create($this);
            $this->fill($data);
            $event = 'created';
        }

        $this->syncOriginal();

        Event::dispatch(EntityEvent::class, [$this, $event]);
    }


    /**
     * Init cached data
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public static function init()
    {
        Cache::delete(get_called_class() . ':all');
        Cache::rememberForever(get_called_class() . ':all', function () {
            $items = static::query()->get();
            $collection = Collection::make($items)->keyBy(function ($item, $key) {
                return $item->id;
            });

            // For product categories NOTE: do not support for now
//            foreach (Category::getGoods() as $goodsCategory) {
//                $category = $collection->first(function ($item) use ($goodsCategory) {
//                    return $item->name === static::getNameFromGoods($goodsCategory);
//                });
//                if (!$category) {
//                    $category = new static();
//                    $category->name = static::getNameFromGoods($goodsCategory);
//                    $category->parentCategoryId = null;
//                    $category->description = 'aus AGR';
//                    $category->save();
//                    $collection->put($category->id, $category);
//                }
//            }

            // For fake categories
            foreach (static::fakeCategories() as $fakeCategory) {
                $category = $collection->first(function ($item) use ($fakeCategory) {
                    return $item->name === $fakeCategory->name;
                });
                if (!$category) {
                    $category = new static();
                    $category->name = $fakeCategory->name;
                    $category->parentCategoryId = $fakeCategory->parentCategoryId;
                    $category->description = $fakeCategory->description;
                    $category->save();
                    $collection->put($category->id, $category);
                }
            }

            return $collection;
        });
    }

    /**
     * @return Collection|static[]
     */
    protected static function fakeCategories()
    {
        return Collection::make([
            [
                'parentCategoryId' => null,
                'name' => self::CATEGORY_FEE_NAME,
                'description' => 'Gebührenartikel für Ausschreibung und Sonderprodukte'
            ],
            [
                'parentCategoryId' => null,
                'name' => self::CATEGORY_TENDER_NAME,
                'description' => 'Ausschreibungsartikel aus AGR'
            ],
            [
                'parentCategoryId' => null,
                'name' => self::CATEGORY_SERVICE_NAME,
                'description' => 'aus AGR'
            ]
        ])->mapInto(static::class);
    }


    /**
     * @param Category|null $goodsCategory
     * @throws \Tan\ERP\Exceptions\ApiErrorException
     * @return static|null
     */
    public static function findByGoodsCategory(?Category $goodsCategory)
    {
        if (!$goodsCategory) {
            return null;
        }

        return static::findByName(static::getNameFromGoods($goodsCategory));
    }


    /**
     * @param string $name
     * @return static|null
     */
    public static function findByName(string $name)
    {
        $category = static::all()->first(function ($item) use ($name) {
            return $item->name === $name;
        });

        return $category;
    }


    /**
     * {@inheritdoc}
     */
    public static function find($id)
    {
        return static::all()->get($id)->first();
    }


    /**
     * {@inheritdoc}
     */
    public static function all()
    {
        if (!Cache::has(get_called_class() . ':all')) {
            static::init();
        }

        return Cache::get(get_called_class() . ':all');
    }


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
        throw new NotSupportedByAGRException();
    }
}
